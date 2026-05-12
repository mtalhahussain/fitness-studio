<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives attendance push from ZKTeco devices.
 *
 * Machine setup (in device web panel or LCD menu):
 *   Server Address : yoursaas.com
 *   Server Port    : 443 (HTTPS) or 80
 *   URL Path       : /api/biometric/push
 *   API Key        : (copy from device registration page)
 *
 * ZKTeco PUSH sends XML or JSON depending on model/firmware.
 * We handle both formats here.
 *
 * Employee ID in machine = user.id in our system.
 * Owner must enroll members with their user.id as Employee Number.
 */
class BiometricPushController extends Controller
{
    public function __construct(private AttendanceService $attendance) {}

    /**
     * ZKTeco ADMS / PUSH SDK endpoint.
     * Called by machine automatically when someone punches.
     */
    public function receive(Request $request)
    {
        // Identify device by API key (sent as header or query param)
        $apiKey = $request->header('X-Api-Key')
            ?? $request->query('api_key')
            ?? $request->input('api_key');

        if (! $apiKey) {
            return response()->json(['error' => 'Missing API key'], 401);
        }

        $device = BiometricDevice::where('api_key', $apiKey)->active()->first();

        if (! $device) {
            return response()->json(['error' => 'Device not registered or inactive'], 401);
        }

        $device->markSeen();

        // Parse the punch log — handle XML and JSON
        $logs = $this->parseLogs($request, $device);

        if (empty($logs)) {
            return response()->json(['ok' => true, 'processed' => 0]);
        }

        $processed = 0;
        $errors    = [];

        foreach ($logs as $log) {
            try {
                $result = $this->processLog($log, $device);
                if ($result) $processed++;
            } catch (\Throwable $e) {
                Log::warning('Biometric log error', [
                    'device'  => $device->serial_number,
                    'log'     => $log,
                    'error'   => $e->getMessage(),
                ]);
                $errors[] = $e->getMessage();
            }
        }

        return response()->json(['ok' => true, 'processed' => $processed, 'errors' => $errors]);
    }

    /**
     * ZKTeco "cdata" style heartbeat / info push — machine checks server alive.
     * Some models send a GET to verify server is reachable.
     */
    public function ping(Request $request)
    {
        return response('OK', 200);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function parseLogs(Request $request, BiometricDevice $device): array
    {
        $contentType = $request->header('Content-Type', '');
        $raw         = $request->getContent();

        // JSON format (newer models, PUSH SDK v3+)
        if (str_contains($contentType, 'json') || str_starts_with(trim($raw), '{') || str_starts_with(trim($raw), '[')) {
            return $this->parseJson($request);
        }

        // XML format (iClock, older models)
        if (str_contains($contentType, 'xml') || str_starts_with(trim($raw), '<')) {
            return $this->parseXml($raw);
        }

        // URL-encoded (some ZKTeco models send form POST)
        if ($request->has('table') || $request->has('Stamp')) {
            return $this->parseFormPost($request);
        }

        return [];
    }

    /**
     * JSON format — PUSH SDK v3 (G3, SpeedFace series etc.)
     * { "records": [{ "employee_id": "5", "time": "2026-05-12 09:00:00", "type": 0 }] }
     */
    private function parseJson(Request $request): array
    {
        $logs = [];

        // Some models wrap in "records", some send array directly
        $records = $request->input('records')
            ?? $request->input('attendance_log')
            ?? (is_array($request->all()) ? $request->all() : []);

        foreach ((array) $records as $rec) {
            $logs[] = [
                'employee_id' => $rec['employee_id'] ?? $rec['EnrollNumber'] ?? $rec['user_id'] ?? null,
                'time'        => $rec['time'] ?? $rec['LogTime'] ?? $rec['timestamp'] ?? null,
                'type'        => $rec['type'] ?? $rec['VerifyMode'] ?? 0, // 0=in, 1=out (not always reliable)
            ];
        }

        return array_filter($logs, fn ($l) => $l['employee_id'] && $l['time']);
    }

    /**
     * XML format — iClock protocol (F18, K40, MA300, UA860 etc.)
     * <Log>
     *   <row pin="5" time="2026-05-12 09:00:00" status="0" />
     * </Log>
     */
    private function parseXml(string $raw): array
    {
        $logs = [];

        try {
            $xml = simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NOERROR);
            if (! $xml) return [];

            // Handle both <Log><row .../></Log> and <attendancelog><record .../>
            $rows = $xml->row ?? $xml->record ?? $xml->Log->row ?? [];

            foreach ($rows as $row) {
                $attrs = (array) $row->attributes();
                $attr  = $attrs['@attributes'] ?? [];

                $logs[] = [
                    'employee_id' => $attr['pin'] ?? $attr['uid'] ?? $attr['EnrollNumber'] ?? null,
                    'time'        => $attr['time'] ?? $attr['LogTime'] ?? null,
                    'type'        => (int) ($attr['status'] ?? $attr['type'] ?? 0),
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('ZKTeco XML parse error: ' . $e->getMessage());
        }

        return array_filter($logs, fn ($l) => $l['employee_id'] && $l['time']);
    }

    /**
     * Form-POST format — iClock legacy (table=ATTLOG&Stamp=...)
     * Stamp field contains newline-separated records:
     * "5\t2026-05-12 09:00:00\t0\t1\t\t0\n..."
     */
    private function parseFormPost(Request $request): array
    {
        $logs  = [];
        $stamp = $request->input('Stamp', '');

        foreach (explode("\n", trim($stamp)) as $line) {
            $line = trim($line);
            if (! $line) continue;

            $parts = preg_split('/\t+/', $line);
            if (count($parts) < 2) continue;

            $logs[] = [
                'employee_id' => $parts[0] ?? null,
                'time'        => $parts[1] ?? null,
                'type'        => (int) ($parts[2] ?? 0),
            ];
        }

        return array_filter($logs, fn ($l) => $l['employee_id'] && $l['time']);
    }

    private function processLog(array $log, BiometricDevice $device): bool
    {
        // employee_id in ZKTeco = user.id in our system
        $user = User::where('id', $log['employee_id'])
            ->where('gym_id', $device->gym_id)
            ->first();

        if (! $user) {
            Log::info('Biometric: unknown employee', [
                'employee_id' => $log['employee_id'],
                'gym_id'      => $device->gym_id,
                'device'      => $device->serial_number,
            ]);
            return false;
        }

        $time = Carbon::parse($log['time']);

        // Use toggle mode — machine punch = check-in OR check-out automatically
        $this->attendance->processLog(
            user:         $user,
            gymId:        $device->gym_id,
            time:         $time,
            source:       'biometric',
            deviceUserId: (string) $log['employee_id'],
        );

        return true;
    }
}
