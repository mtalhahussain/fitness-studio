<?php

namespace App\Http\Controllers\Api;

use App\Biometric\BiometricPunchProcessor;
use App\Biometric\Drivers\ZKTecoDriver;
use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use App\Services\UnknownBiometricDevices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Fixed-URL endpoint for ZKTeco ADMS / iClock push (also eSSL, ZK-based Realtime).
 *
 * Machine setup: Server Address = our host, Port = 80/443, URL path = /api/biometric/push.
 * Devices identify themselves via the iClock `SN` query param (matched against
 * biometric_devices.serial_number). `api_key` is still accepted as a fallback for
 * manual testing (Postman/curl).
 *
 * Other brands use per-device token URLs — see BiometricWebhookController.
 */
class BiometricPushController extends Controller
{
    public function __construct(
        private ZKTecoDriver $driver,
        private BiometricPunchProcessor $processor,
        private UnknownBiometricDevices $unknownDevices,
    ) {}

    public function receive(Request $request)
    {
        $device = $this->findDevice($request);

        if (! $device) {
            $this->recordUnknown($request, 'push');
            return response()->json(['error' => 'Device not registered or inactive'], 401);
        }

        // Mark seen even when disabled, so the owner can see the machine is still trying to connect.
        $device->markSeen();
        $device->storePayload($request->getContent() ?: http_build_query($request->all()));

        if (! $device->is_active) {
            return response()->json(['error' => 'Device not registered or inactive'], 401);
        }

        foreach ($this->driver->parse($request, $device) as $log) {
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

        return $this->driver->acknowledge();
    }

    /** ZKTeco heartbeat — the machine checks the server is alive. */
    public function ping(Request $request)
    {
        $device = $this->findDevice($request);

        $device ? $device->markSeen() : $this->recordUnknown($request, 'ping');

        return response('OK', 200);
    }

    /** Registered device by SN (or api_key fallback), active or not — callers check is_active. */
    private function findDevice(Request $request): ?BiometricDevice
    {
        $serialNumber = $this->serialNumber($request);

        if ($serialNumber) {
            $device = BiometricDevice::where('serial_number', $serialNumber)->first();
            if ($device) {
                return $device;
            }
        }

        $apiKey = $request->header('X-Api-Key')
            ?? $request->query('api_key')
            ?? $request->input('api_key');

        if (! $apiKey) {
            return null;
        }

        return BiometricDevice::where('api_key', $apiKey)->first();
    }

    private function serialNumber(Request $request): ?string
    {
        $sn = $request->query('SN') ?? $request->input('SN');

        return is_string($sn) && $sn !== '' ? $sn : null;
    }

    private function recordUnknown(Request $request, string $endpoint): void
    {
        if ($sn = $this->serialNumber($request)) {
            $this->unknownDevices->record($sn, $request->ip(), $endpoint);
        }
    }
}
