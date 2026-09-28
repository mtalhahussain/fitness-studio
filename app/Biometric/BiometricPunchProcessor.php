<?php

namespace App\Biometric;

use App\Models\BiometricDevice;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\BiometricAttendanceService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Turns a PunchLog from any brand into attendance for the device's gym. */
class BiometricPunchProcessor
{
    public function __construct(
        private AttendanceService $attendance,
        private BiometricAttendanceService $biometricAttendance,
    ) {}

    public function process(PunchLog $log, BiometricDevice $device): bool
    {
        $employeeId = trim($log->employeeId);
        if ($employeeId === '') {
            return false;
        }

        $user = $this->resolveOrCreateUser($employeeId, $device);

        if (! $user) {
            Log::info('Biometric: unknown employee', [
                'employee_id' => $employeeId,
                'gym_id'      => $device->gym_id,
                'device_id'   => $device->id,
            ]);
            return false;
        }

        $time = $log->time;

        if ($this->biometricAttendance->isDuplicate($user->id, $device->gym_id, $time)) {
            Log::info('Biometric push: duplicate punch skipped', [
                'user_id'     => $user->id,
                'employee_id' => $employeeId,
                'time'        => $time,
                'device_id'   => $device->id,
            ]);
            return true;
        }

        $open = $this->attendance->findOpenSession($user->id, $device->gym_id);

        // Machine said "in" but member is already inside, or "out" with nothing open: ignore.
        if (($log->type === PunchLog::IN && $open) || ($log->type === PunchLog::OUT && ! $open)) {
            return true;
        }

        // null type (and consistent in/out) → toggle, exactly as ZKTeco always worked
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
                'user_id'     => $user->id,
                'gym_id'      => $gymId,
                'employee_id' => $employeeId,
                'error'       => $e->getMessage(),
            ]);
        }

        Log::info('Biometric auto-created member from device', [
            'user_id'     => $user->id,
            'gym_id'      => $gymId,
            'employee_id' => $employeeId,
            'device_id'   => $device->id,
        ]);

        return $user;
    }
}
