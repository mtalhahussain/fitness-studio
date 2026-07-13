<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\BiometricAttendanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Receives attendance push from ZKTeco devices.
 *
 * Machine setup (in device web panel or LCD menu):
 *   Server Address : yoursaas.com
 *   Server Port    : 443 (HTTPS) or 80
 *   URL Path       : /api/biometric/push
 *
 * Devices identify themselves via the standard iClock `SN` query param
 * (their serial number, matched against biometric_devices.serial_number) --
 * no custom header configuration is required. `api_key` is still accepted
 * as a fallback for manual testing (Postman/curl).
 *
 * ZKTeco PUSH sends XML or JSON depending on model/firmware.
 * We handle both formats here.
 *
 * Employee ID in machine = user.id in our system.
 * Owner must enroll members with their user.id as Employee Number.
 */
class BiometricPushController extends Controller
{
    public function __construct(
        private AttendanceService $attendance,
        private BiometricAttendanceService $biometricAttendance,
    ) {}

    /**
     * ZKTeco ADMS / PUSH SDK endpoint.
     * Called by machine automatically when someone punches.
     */
    public function receive(Request $request)
    {
        $device = $this->resolveDevice($request);

        if (! $device) {
            return response()->json(['error' => 'Device not registered or inactive'], 401);
        }

        $device->markSeen();

        // Parse the punch log — handle XML and JSON
        $logs = $this->parseLogs($request, $device);

        if (empty($logs)) {
            return response('OK', 200);
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

        return response('OK', 200);
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

    private function resolveDevice(Request $request): ?BiometricDevice
    {
        $serialNumber = $request->query('SN') ?? $request->input('SN');

        if ($serialNumber) {
            $device = BiometricDevice::where('serial_number', $serialNumber)->active()->first();
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

        return BiometricDevice::where('api_key', $apiKey)->active()->first();
    }

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
        $employeeId = trim((string) ($log['employee_id'] ?? ''));
        if ($employeeId === '') {
            return false;
        }

        $user = $this->resolveOrCreateUser($employeeId, $device);

        if (! $user) {
            Log::info('Biometric: unknown employee', [
                'employee_id' => $employeeId,
                'gym_id'      => $device->gym_id,
                'device'      => $device->serial_number,
            ]);
            return false;
        }

        $time = Carbon::parse($log['time']);

        if ($this->biometricAttendance->isDuplicate($user->id, $device->gym_id, $time)) {
            Log::info('Biometric push: duplicate punch skipped', [
                'user_id'     => $user->id,
                'employee_id' => $employeeId,
                'time'        => $time,
                'device'      => $device->serial_number,
            ]);
            return true;
        }

        // Use toggle mode — machine punch = check-in OR check-out automatically
        $this->attendance->processLog(
            user:         $user,
            gymId:        $device->gym_id,
            time:         $time,
            source:       'biometric',
            deviceUserId: $employeeId,
        );

        return true;
    }

    private function resolveOrCreateUser(string $employeeId, BiometricDevice $device): ?User
    {
        $gymId = $device->gym_id;

        $byCode = User::where('gym_id', $gymId)
            ->where('biometric_code', $employeeId)
            ->first();

        if ($byCode) {
            return $byCode;
        }

        $byId = User::where('gym_id', $gymId)
            ->where('id', $employeeId)
            ->first();

        if ($byId) {
            if (empty($byId->biometric_code)) {
                $byId->update(['biometric_code' => $employeeId]);
            }

            return $byId;
        }

        $byPhone = User::where('gym_id', $gymId)
            ->where('phone', $employeeId)
            ->first();

        if ($byPhone) {
            if (empty($byPhone->biometric_code)) {
                $byPhone->update(['biometric_code' => $employeeId]);
            }

            return $byPhone;
        }

        return $this->autoCreateMemberFromDevice($employeeId, $device);
    }

    private function autoCreateMemberFromDevice(string $employeeId, BiometricDevice $device): ?User
    {
        $gymId = $device->gym_id;
        $email = sprintf(
            'device-%s-%s@local.member',
            preg_replace('/[^A-Za-z0-9]/', '', $employeeId) ?: 'member',
            Str::lower(Str::random(8))
        );

        $user = User::create([
            'gym_id'         => $gymId,
            'name'           => 'Device Member ' . $employeeId,
            'email'          => $email,
            'password'       => Str::random(32),
            'status'         => 'active',
            'biometric_code' => $employeeId,
        ]);

        try {
            if (! $user->hasRole('member')) {
                $user->assignRole('member');
            }
        } catch (\Throwable $e) {
            Log::warning('Biometric auto-create role assign failed', [
                'user_id'      => $user->id,
                'gym_id'       => $gymId,
                'employee_id'  => $employeeId,
                'error'        => $e->getMessage(),
            ]);
        }

        Log::info('Biometric auto-created member from device', [
            'user_id'      => $user->id,
            'gym_id'       => $gymId,
            'employee_id'  => $employeeId,
            'device'       => $device->serial_number,
        ]);

        return $user;
    }
}
