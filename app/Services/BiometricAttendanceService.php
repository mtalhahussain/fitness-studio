<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Handles attendance sync from ZKTeco (and compatible) biometric devices.
 *
 * ZKTeco integration flow:
 *   Device → Push SDK / Pull API → BiometricSyncController → BiometricAttendanceService → AttendanceService
 *
 * A device log contains:
 *   device_user_id  : enrollment number on the device (maps to our user)
 *   punch_time      : timestamp of the punch
 *   punch_type      : 0=check-in, 1=check-out, null=unknown (toggle mode)
 *   device_id       : optional device identifier
 */
class BiometricAttendanceService
{
    // ZKTeco standard punch type constants
    const PUNCH_CHECK_IN  = 0;
    const PUNCH_CHECK_OUT = 1;

    // Duplicate window: ignore same device_user_id punches within N seconds
    const DUPLICATE_WINDOW_SECONDS = 60;

    public function __construct(private AttendanceService $attendanceService) {}

    /**
     * Process a batch of logs from ZKTeco device.
     * Returns a summary of processed, skipped, and failed records.
     */
    public function syncBatch(array $logs, ?int $gymId): array
    {
        $result = ['processed' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];

        foreach ($logs as $log) {
            try {
                $outcome = $this->processLog($log, $gymId);

                if ($outcome === null) {
                    $result['skipped']++;
                } else {
                    $result['processed']++;
                }
            } catch (\Throwable $e) {
                $result['failed']++;
                $result['errors'][] = [
                    'log'   => $log,
                    'error' => $e->getMessage(),
                ];

                Log::warning('BiometricAttendanceService: failed to process log', [
                    'log'   => $log,
                    'error' => $e->getMessage(),
                    'gym'   => $gymId,
                ]);
            }
        }

        return $result;
    }

    /**
     * Process a single biometric log.
     * Returns null if the log is a duplicate and was skipped.
     */
    public function processLog(array $log, ?int $gymId): ?Attendance
    {
        $deviceUserId = (string) ($log['device_user_id'] ?? $log['user_id'] ?? '');
        $punchTime    = Carbon::parse($log['punch_time'] ?? $log['timestamp'] ?? now());
        $punchType    = $log['punch_type'] ?? null;  // null = toggle mode

        if (empty($deviceUserId)) {
            throw new \InvalidArgumentException('device_user_id is required.');
        }

        // Resolve device enrollment number to our system user
        $user = $this->resolveUser($deviceUserId, $gymId);

        if (! $user) {
            throw new \RuntimeException("No user found for device_user_id [{$deviceUserId}] in gym [{$gymId}].");
        }

        // Reject duplicate punch within the deduplication window
        if ($this->isDuplicate($user->id, $gymId, $punchTime)) {
            Log::info('BiometricAttendanceService: duplicate punch skipped', [
                'user_id'        => $user->id,
                'device_user_id' => $deviceUserId,
                'punch_time'     => $punchTime,
            ]);

            return null;
        }

        // Determine action based on punch_type (or use toggle mode)
        $attendance = match (true) {
            $punchType === self::PUNCH_CHECK_IN  => $this->handleBiometricCheckIn($user, $gymId, $punchTime, $deviceUserId),
            $punchType === self::PUNCH_CHECK_OUT => $this->handleBiometricCheckOut($user, $gymId, $punchTime),
            default                              => $this->handleToggle($user, $gymId, $punchTime, $deviceUserId),
        };

        return $attendance;
    }

    /**
     * Map ZKTeco device_user_id (enrollment number) to our User record.
     *
     * Strategy: Users register with their device enrollment number stored
     * in the `device_user_id` field of any existing attendance, or we can
     * add a dedicated `biometric_id` column to users later.
     *
     * Current implementation: match by direct lookup in attendances table
     * (already linked from previous syncs) or fallback to user->id equality
     * (when device_user_id matches our user primary key — common in small gyms).
     */
    public function resolveUser(string $deviceUserId, ?int $gymId): ?User
    {
        // 1. Look for a previously linked user via attendance record
        $linked = Attendance::forGym($gymId)
            ->where('device_user_id', $deviceUserId)
            ->where('source', 'biometric')
            ->latest()
            ->value('user_id');

        if ($linked) {
            return User::find($linked);
        }

        // 2. Fallback: treat device_user_id as our user's primary key
        //    (Works when gym maps users 1-to-1 on device enrollment)
        return User::forGym($gymId)
            ->where('id', $deviceUserId)
            ->first();
    }

    /**
     * Check if a punch from the same user within DUPLICATE_WINDOW_SECONDS already exists.
     */
    public function isDuplicate(int $userId, ?int $gymId, Carbon $punchTime): bool
    {
        $windowStart = (clone $punchTime)->subSeconds(self::DUPLICATE_WINDOW_SECONDS);
        $windowEnd   = (clone $punchTime)->addSeconds(self::DUPLICATE_WINDOW_SECONDS);

        return Attendance::forUser($userId)
            ->forGym($gymId)
            ->where('check_in_time', '>=', $windowStart)
            ->where('check_in_time', '<=', $windowEnd)
            ->exists();
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function handleBiometricCheckIn(User $user, ?int $gymId, Carbon $time, string $deviceUserId): Attendance
    {
        // If open session exists for biometric, silently ignore (device may re-send)
        $open = $this->attendanceService->findOpenSession($user->id, $gymId);

        if ($open && $open->source === 'biometric') {
            return $open;
        }

        return $this->attendanceService->checkIn($user, $gymId, $time, 'biometric', $deviceUserId);
    }

    private function handleBiometricCheckOut(User $user, ?int $gymId, Carbon $time): Attendance
    {
        return $this->attendanceService->checkOut($user, $gymId, $time, 'biometric');
    }

    private function handleToggle(User $user, ?int $gymId, Carbon $time, string $deviceUserId): Attendance
    {
        $result = $this->attendanceService->processLog($user, $gymId, $time, 'biometric', $deviceUserId);
        return $result['attendance'];
    }
}
