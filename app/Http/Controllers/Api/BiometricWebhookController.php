<?php

namespace App\Http\Controllers\Api;

use App\Biometric\BiometricPunchProcessor;
use App\Biometric\DriverRegistry;
use App\Biometric\Drivers\GenericWebhookDriver;
use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use App\Services\UnknownBiometricDevices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Per-device URL for token brands (Hikvision, Generic webhook):
 *   GET  /api/biometric/hook/{token}  heartbeat
 *   POST /api/biometric/hook/{token}  events
 * The token alone identifies the device and its gym.
 */
class BiometricWebhookController extends Controller
{
    public function __construct(
        private DriverRegistry $drivers,
        private BiometricPunchProcessor $processor,
        private UnknownBiometricDevices $unknownDevices,
    ) {}

    public function __invoke(Request $request, string $token)
    {
        $device = BiometricDevice::where('webhook_token', $token)->first();
        $driver = null;

        if ($device) {
            try {
                $driver = $this->drivers->get($device->brand);
            } catch (\InvalidArgumentException) {
                // The brand was removed from config after the device was registered:
                // treat exactly like an unknown token below.
                $driver = null;
            }
        }

        if (! $device || ! $driver || $driver->identifiesBy() !== 'token') {
            $this->unknownDevices->record('token:' . mb_substr($token, 0, 6) . '…', $request->ip(), 'hook');
            abort(404);
        }

        $device->markSeen();

        if ($request->isMethod('GET')) {
            return response('OK', 200);
        }

        $rawBody = $request->getContent();
        $decoded = json_decode($rawBody, true);
        $isHeartbeat = is_array($decoded)
            && is_string($decoded['eventType'] ?? null)
            && strcasecmp($decoded['eventType'], 'heartBeat') === 0;

        // Hikvision sends JSON heartbeats every ~20s; keep last_payload showing the last
        // REAL event for the Setup panel instead of getting clobbered by heartbeat noise.
        if (! $isHeartbeat) {
            $device->storePayload($rawBody ?: json_encode($request->except(array_keys($request->allFiles()))));
        }

        if (! $device->is_active) {
            return response()->json(['error' => 'Device inactive'], 401);
        }

        if ($driver instanceof GenericWebhookDriver && ! $driver->secretMatches($request, $device)) {
            return response()->json(['error' => 'Invalid secret'], 401);
        }

        try {
            $logs = $driver->parse($request, $device);
        } catch (\Throwable $e) {
            // Never make the machine retry-loop; the payload is kept for debugging.
            Log::warning('Biometric webhook: parse failed', ['device_id' => $device->id, 'error' => $e->getMessage()]);

            return $driver->acknowledge();
        }

        if ($logs === [] && $rawBody !== '' && ! $isHeartbeat) {
            Log::warning('Biometric webhook: data received but no punches extracted', ['device_id' => $device->id, 'brand' => $device->brand]);
        }

        foreach ($logs as $log) {
            try {
                $this->processor->process($log, $device);
            } catch (\Throwable $e) {
                Log::warning('Biometric webhook: punch failed', [
                    'device_id'   => $device->id,
                    'employee_id' => $log->employeeId,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        return $driver->acknowledge();
    }
}
