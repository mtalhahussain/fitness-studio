<?php

namespace App\Http\Controllers\Iclock;

use App\Biometric\BiometricPunchProcessor;
use App\Biometric\DeviceCommandQueue;
use App\Biometric\Drivers\ZKTecoDriver;
use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use App\Services\UnknownBiometricDevices;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * ZKTeco ADMS / iClock push protocol at the root paths the firmware hardcodes:
 *   GET  /iclock/cdata       handshake (options)
 *   POST /iclock/cdata       data upload (?table=ATTLOG | OPERLOG | ...)
 *   GET  /iclock/getrequest  command poll
 *   POST /iclock/devicecmd   command result
 *
 * Every device is identified by its SN query param (biometric_devices.serial_number).
 */
class IclockController extends Controller
{
    public function __construct(
        private ZKTecoDriver $driver,
        private BiometricPunchProcessor $processor,
        private UnknownBiometricDevices $unknownDevices,
        private DeviceCommandQueue $commands,
    ) {}

    public function handshake(Request $request): Response
    {
        $device = $this->device($request, 'handshake');

        // Unknown or disabled machines get no options, so they don't start uploading.
        if (! $device?->is_active) {
            return $this->text('OK');
        }

        return $this->text($device->admsOptions());
    }

    public function upload(Request $request): Response
    {
        $device = $this->device($request, 'cdata');

        if (! $device) {
            return $this->text('Device not registered', 401);
        }

        $raw = $request->getContent();
        $device->storePayload($raw);

        if (! $device->is_active) {
            return $this->text('Device inactive', 401);
        }

        $table = strtoupper((string) $request->query('table', ''));

        return $table === 'ATTLOG'
            ? $this->storeAttlog($request, $device, $raw)
            : $this->acceptOtherTable($request, $device, $table, $raw);
    }

    /** Pending commands as "C:<cmd_id>:<command>" lines, or "OK" when there is nothing to do. */
    public function getrequest(Request $request): Response
    {
        $device = $this->device($request, 'getrequest');

        if (! $device?->is_active) {
            return $this->text('OK');
        }

        $commands = $this->commands->nextBatch($device);

        Log::info('Biometric diagnostics: command poll', [
            'serial_number' => $device->serial_number,
            'commands_returned' => $commands->count(),
            'command_ids' => $commands->pluck('cmd_id')->all(),
        ]);

        return $this->text($commands->isEmpty()
            ? 'OK'
            : $commands->map->toAdmsLine()->implode("\n") . "\n");
    }

    /** Machine reports command results: "ID=<cmd_id>&Return=<code>&CMD=<type>" per line. */
    public function devicecmd(Request $request): Response
    {
        $device = $this->device($request, 'devicecmd');

        if (! $device) {
            return $this->text('Device not registered', 401);
        }

        $updated = $this->commands->recordResults($device, $request->getContent());

        Log::info('Biometric diagnostics: command results', [
            'serial_number' => $device->serial_number,
            'commands_updated' => $updated,
        ]);

        return $this->text('OK');
    }

    /** "OK: n" where n = rows accepted (saved, duplicate or unknown PIN) so the machine does not resend them. */
    private function storeAttlog(Request $request, BiometricDevice $device, string $raw): Response
    {
        $logs = $this->driver->parseAttlog($raw, $device);

        Log::info('Biometric diagnostics: attendance upload', [
            'serial_number' => $device->serial_number,
            'punches_parsed' => count($logs),
        ]);

        foreach ($logs as $log) {
            try {
                $this->processor->process($log, $device);
            } catch (\Throwable $e) {
                Log::warning('Biometric log error', [
                    'device' => $device->serial_number,
                    'log'    => ['employee_id' => $log->employeeId, 'time' => (string) $log->time],
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        $this->saveStamp($device, 'attlog_stamp', $request->query('Stamp'));

        return $this->text('OK: ' . count($logs));
    }

    /** OPERLOG, USERINFO, options etc. Acknowledged so the machine moves on; handled in a later phase. */
    private function acceptOtherTable(Request $request, BiometricDevice $device, string $table, string $raw): Response
    {
        $lines = array_filter(preg_split('/\r\n|\r|\n/', $raw), fn ($l) => trim($l) !== '');

        if ($table === 'OPERLOG') {
            $this->saveStamp($device, 'operlog_stamp', $request->query('OpStamp') ?? $request->query('Stamp'));
        }

        return $this->text('OK: ' . count($lines));
    }

    private function saveStamp(BiometricDevice $device, string $column, mixed $stamp): void
    {
        if (is_string($stamp) && $stamp !== '') {
            $device->forceFill([$column => mb_substr($stamp, 0, 50)])->saveQuietly();
        }
    }

    /** Registered device for this SN (active or not), marked seen; unknown SNs go to the diagnostics cache. */
    private function device(Request $request, string $endpoint): ?BiometricDevice
    {
        $sn = $request->query('SN');

        Log::info('Biometric diagnostics: request', [
            'endpoint' => $endpoint,
            'method' => $request->method(),
            'path' => $request->path(),
            'serial_number' => is_string($sn) ? mb_substr($sn, 0, 100) : null,
            'ip' => $request->ip(),
            'table' => is_string($request->query('table')) ? mb_substr($request->query('table'), 0, 50) : null,
        ]);

        if (! is_string($sn) || $sn === '') {
            Log::warning('Biometric diagnostics: missing serial number');
            return null;
        }

        $device = BiometricDevice::where('serial_number', $sn)->first();

        if (! $device) {
            Log::warning('Biometric diagnostics: unregistered serial number', ['serial_number' => mb_substr($sn, 0, 100)]);
            $this->unknownDevices->record($sn, $request->ip(), "iclock/{$endpoint}");
            return null;
        }

        $device->markSeen();

        Log::info('Biometric diagnostics: device matched', [
            'serial_number' => $device->serial_number,
            'is_active' => (bool) $device->is_active,
        ]);

        return $device;
    }

    private function text(string $body, int $status = 200): Response
    {
        return response($body, $status)->header('Content-Type', 'text/plain');
    }
}
