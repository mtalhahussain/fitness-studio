<?php

namespace App\Services;

use App\Events\MemberCheckedIn;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    /**
     * Unified entry point for both manual and biometric sources.
     *
     * ZKTeco devices sometimes don't distinguish check-in from check-out —
     * they just send a "punch". We use toggle mode: if no open session exists,
     * treat as check-in; if an open session exists, treat as check-out.
     */
    public function processLog(
        User $user,
        ?int $gymId,
        Carbon $time,
        string $source = 'manual',
        ?string $deviceUserId = null
    ): array {
        $open = $this->findOpenSession($user->id, $gymId);

        if ($open) {
            $record = $this->performCheckOut($open, $time);
            return ['action' => 'check_out', 'attendance' => $record];
        }

        $record = $this->performCheckIn($user->id, $gymId, $time, $source, $deviceUserId);
        return ['action' => 'check_in', 'attendance' => $record];
    }

    /**
     * Manual check-in — fails if an open session already exists.
     */
    public function checkIn(
        User $user,
        ?int $gymId,
        Carbon $time,
        string $source = 'manual',
        ?string $deviceUserId = null
    ): Attendance {
        $open = $this->findOpenSession($user->id, $gymId);

        if ($open) {
            throw new \RuntimeException(
                "Already checked in at {$open->check_in_time->format('H:i')}. Please check out first.",
                409
            );
        }

        return $this->performCheckIn($user->id, $gymId, $time, $source, $deviceUserId);
    }

    /**
     * Manual check-out — fails if no open session exists.
     * Handles late check-out (next day) by searching without date boundary.
     */
    public function checkOut(
        User $user,
        ?int $gymId,
        Carbon $time,
        string $source = 'manual'
    ): Attendance {
        $open = $this->findOpenSession($user->id, $gymId);

        if (! $open) {
            throw new \RuntimeException('No open check-in found. Please check in first.', 422);
        }

        return $this->performCheckOut($open, $time);
    }

    /**
     * Get today's attendance for a gym, with optional filters.
     */
    public function getTodayAttendance(?int $gymId, array $filters = [])
    {
        $query = Attendance::forGym($gymId)
            ->today()
            ->with('user:id,name,email,phone')
            ->latest('check_in_time');

        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (! empty($filters['status'])) {
            match ($filters['status']) {
                'checked_in'  => $query->open(),
                'checked_out' => $query->whereNotNull('check_out_time'),
                default       => null,
            };
        }

        if (! empty($filters['search'])) {
            $query->whereHas('user', fn ($q) => $q
                ->where('name', 'like', "%{$filters['search']}%")
                ->orWhere('email', 'like', "%{$filters['search']}%")
            );
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Get attendance summary counts for today.
     */
    public function getTodaySummary(?int $gymId): array
    {
        $base = Attendance::forGym($gymId)->today();

        return [
            'total'       => (clone $base)->count(),
            'checked_in'  => (clone $base)->open()->count(),
            'checked_out' => (clone $base)->whereNotNull('check_out_time')->count(),
            'biometric'   => (clone $base)->biometric()->count(),
            'manual'      => (clone $base)->manual()->count(),
        ];
    }

    // ─── Internal helpers ────────────────────────────────────────────────────

    public function findOpenSession(int $userId, ?int $gymId): ?Attendance
    {
        // No date boundary — handles late checkouts spanning midnight
        return Attendance::forUser($userId)
            ->forGym($gymId)
            ->open()
            ->latest('check_in_time')
            ->first();
    }

    private function performCheckIn(
        int $userId,
        ?int $gymId,
        Carbon $time,
        string $source,
        ?string $deviceUserId
    ): Attendance {
        $attendance = Attendance::create([
            'gym_id'         => $gymId,
            'user_id'        => $userId,
            'check_in_time'  => $time,
            'source'         => $source,
            'device_user_id' => $deviceUserId,
        ]);

        MemberCheckedIn::dispatch($attendance->load('user'));

        return $attendance;
    }

    private function performCheckOut(Attendance $session, Carbon $time): Attendance
    {
        $session->update(['check_out_time' => $time]);
        return $session->fresh('user');
    }
}
