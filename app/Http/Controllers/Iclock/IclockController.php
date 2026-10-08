<?php

namespace App\Http\Controllers\Iclock;

use App\Biometric\BiometricPunchProcessor;
use App\Biometric\AccPush;
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
        private AccPush $acc,
    ) {}

    public function handshake(Request $request): Response
    {
        $device = $this->device($request, 'handshake');

        // Unknown or disabled machines get no options, so they don't start uploading.
        if (! $device?->is_active) {
            return $this->text('OK');
        }

        if (strtolower((string) $request->query('DeviceType')) === 'acc') {
            $this->acc->register($device);
        }

        return $device->acc_push_state
            ? $this->pushText($this->acc->options($device, true))
            : $this->text($device->admsOptions());
    }

    public function registry(Request $request): Response
    {
        $device = $this->device($request, 'registry');
        if (! $device?->is_active) {
            return $this->text('Device not registered or inactive', 401);
        }
        $this->acc->register($device);
        return $this->pushText('RegistryCode=' . $device->acc_push_state['registry_code'] . "\n");
    }

    public function push(Request $request): Response
    {
        $device = $this->device($request, 'push');
        if (! $device?->is_active || ! $device->acc_push_state) {
            return $this->text('Device not registered or inactive', 401);
        }
        return $this->pushText($this->acc->options($device));
    }

    public function ping(Request $request): Response
    {
        return $this->device($request, 'ping')?->is_active
            ? $this->text('OK') : $this->text('Device not registered or inactive', 401);
    }

    private function pushText(string $body): Response
    {
        return response($body)->header('Content-Type', 'application/push; charset=UTF-8')
            ->header('Content-Length', (string) strlen($body));
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

        if ($device->acc_push_state) {
            if ($table === 'RTLOG' || ($table === 'TABLEDATA'
                && strtolower((string) $request->query('tablename')) === 'transaction')) {
                $logs = $this->acc->parse($raw, $device);
                Log::info('Biometric diagnostics: ACC attendance upload', [
                    'serial_number' => $device->serial_number,
                    'table' => $table,
                    'punches_parsed' => count($logs),
                ]);
                // Let a processing failure return 500 so the device retries the upload.
                foreach ($logs as $log) {
                    $this->processor->process($log, $device);
                }
                return $this->text('OK');
            }
            if ($table !== 'ATTLOG') {
                return $this->text('OK');
            }
        }

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
            : $commands->map(fn ($command) => $device->acc_push_state
                ? "C:{$command->cmd_id}:" . $this->acc->command($command->command)
                : $command->toAdmsLine())->implode("\n") . "\n");
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

        // Only protocol metadata: never log arbitrary query values or credentials.
        $protocol = [];
        foreach (['options', 'pushver', 'PushVersion', 'Language', 'DeviceType', 'AuthType'] as $field) {
            $value = $request->query($field);
            if (is_string($value)) {
                $protocol[$field] = mb_substr($value, 0, 100);
            }
        }

        Log::info('Biometric diagnostics: request', [
            'endpoint' => $endpoint,
            'method' => $request->method(),
            'path' => $request->path(),
            'serial_number' => is_string($sn) ? mb_substr($sn, 0, 100) : null,
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 250),
            'protocol' => $protocol,
            'host' => $request->getHost(),
            'scheme' => $request->getScheme(),
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
        return response($body, $status)->header('Content-Type', 'text/plain')
            ->header('Content-Length', (string) strlen($body));
    }
}
