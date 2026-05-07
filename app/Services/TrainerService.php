<?php

namespace App\Services;

use App\Models\TrainerProfile;
use App\Models\TrainingSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TrainerService extends BaseService
{
    // ── Trainer CRUD ─────────────────────────────────────────────────────────

    public function createTrainer(array $data, ?int $gymId): User
    {
        return DB::transaction(function () use ($data, $gymId) {
            $trainer = User::create([
                'gym_id'   => $gymId,
                'name'     => $data['name'],
                'email'    => $data['email'],
                'phone'    => $data['phone'] ?? null,
                'password' => bcrypt($data['password'] ?? str()->random(12)),
                'status'   => 'active',
            ]);

            $trainer->assignRole('trainer');

            TrainerProfile::create([
                'user_id'          => $trainer->id,
                'gym_id'           => $gymId,
                'specialization'   => $data['specialization'],
                'bio'              => $data['bio'] ?? null,
                'experience_years' => $data['experience_years'] ?? 0,
                'certifications'   => $data['certifications'] ?? [],
                'hourly_rate'      => $data['hourly_rate'] ?? null,
            ]);

            return $trainer->load('trainerProfile');
        });
    }

    public function updateTrainer(User $trainer, array $data): User
    {
        return DB::transaction(function () use ($trainer, $data) {
            $trainer->update(array_filter(
                array_intersect_key($data, array_flip(['name', 'phone', 'status'])),
                fn ($v) => ! is_null($v)
            ));

            $profileData = array_intersect_key($data, array_flip([
                'specialization', 'bio', 'experience_years',
                'certifications', 'hourly_rate', 'is_active',
            ]));

            if (! empty($profileData)) {
                $trainer->trainerProfile()->updateOrCreate(
                    ['user_id' => $trainer->id],
                    $profileData
                );
            }

            return $trainer->fresh('trainerProfile');
        });
    }

    public function getTrainers(?int $gymId, array $filters = [])
    {
        $query = User::trainers()
            ->forGym($gymId)
            ->with(['trainerProfile', 'assignedMembers:id,name,email'])
            ->withCount(['trainingSessions', 'assignedMembers']);

        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")
                ->orWhereHas('trainerProfile', fn ($q) => $q->where('specialization', 'like', "%{$s}%"))
            );
        }

        if (! empty($filters['specialization'])) {
            $query->whereHas('trainerProfile', fn ($q) => $q
                ->where('specialization', $filters['specialization'])
            );
        }

        return $query->latest()->paginate($filters['per_page'] ?? 15);
    }

    // ── Member Assignment ─────────────────────────────────────────────────────

    public function assignMember(User $trainer, User $member, ?int $gymId, array $data = []): void
    {
        $this->validateBothBelongToGym($trainer, $member, $gymId);

        // If already assigned (even inactive), restore it
        $existing = DB::table('trainer_member')
            ->where('gym_id', $gymId)
            ->where('trainer_id', $trainer->id)
            ->where('member_id', $member->id)
            ->first();

        if ($existing) {
            if ($existing->is_active) {
                throw new \RuntimeException('Member is already assigned to this trainer.', 409);
            }

            DB::table('trainer_member')
                ->where('id', $existing->id)
                ->update(['is_active' => true, 'assigned_at' => now(), 'unassigned_at' => null, 'notes' => $data['notes'] ?? $existing->notes]);

            return;
        }

        DB::table('trainer_member')->insert([
            'gym_id'      => $gymId,
            'trainer_id'  => $trainer->id,
            'member_id'   => $member->id,
            'notes'       => $data['notes'] ?? null,
            'is_active'   => true,
            'assigned_at' => now(),
        ]);
    }

    public function unassignMember(User $trainer, User $member, ?int $gymId): void
    {
        $affected = DB::table('trainer_member')
            ->where('gym_id', $gymId)
            ->where('trainer_id', $trainer->id)
            ->where('member_id', $member->id)
            ->where('is_active', true)
            ->update(['is_active' => false, 'unassigned_at' => now()]);

        if (! $affected) {
            throw new \RuntimeException('Assignment not found.', 404);
        }
    }

    public function getAssignedMembers(User $trainer, ?int $gymId, array $filters = [])
    {
        $query = $trainer->assignedMembers()
            ->forGym($gymId)
            ->withCount('memberSessions');

        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")
            );
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    // ── Training Sessions ─────────────────────────────────────────────────────

    public function createSession(User $trainer, ?int $gymId, array $data): TrainingSession
    {
        if (! empty($data['member_id'])) {
            $member = User::forGym($gymId)->findOrFail($data['member_id']);
            $this->validateBothBelongToGym($trainer, $member, $gymId);
        }

        // Prevent scheduling overlap for the same trainer
        $scheduledAt  = Carbon::parse($data['scheduled_at']);
        $endTime      = (clone $scheduledAt)->addMinutes($data['duration_mins'] ?? 60);

        $conflict = TrainingSession::forTrainer($trainer->id)
            ->where('status', 'scheduled')
            ->where(fn ($q) => $q
                ->whereBetween('scheduled_at', [$scheduledAt, $endTime])
                ->orWhereRaw('DATE_ADD(scheduled_at, INTERVAL duration_mins MINUTE) BETWEEN ? AND ?', [$scheduledAt, $endTime])
            )
            ->first();

        if ($conflict) {
            throw new \RuntimeException(
                "Trainer has a conflicting session at {$conflict->scheduled_at->format('H:i')} on {$conflict->scheduled_at->toDateString()}.",
                409
            );
        }

        return TrainingSession::create([
            'gym_id'        => $gymId,
            'trainer_id'    => $trainer->id,
            'member_id'     => $data['member_id'] ?? null,
            'title'         => $data['title'],
            'notes'         => $data['notes'] ?? null,
            'scheduled_at'  => $scheduledAt,
            'duration_mins' => $data['duration_mins'] ?? 60,
            'session_type'  => $data['session_type'] ?? 'personal',
            'status'        => 'scheduled',
        ]);
    }

    public function updateSession(TrainingSession $session, array $data): TrainingSession
    {
        $session->update(array_filter($data, fn ($v) => ! is_null($v)));
        return $session->fresh(['trainer', 'member']);
    }

    public function getSchedule(User $trainer, ?int $gymId, array $filters = [])
    {
        $query = TrainingSession::forGym($gymId)
            ->forTrainer($trainer->id)
            ->with(['member:id,name,email,phone'])
            ->orderBy('scheduled_at');

        $from = $filters['from'] ?? now()->startOfWeek()->toDateString();
        $to   = $filters['to']   ?? now()->endOfWeek()->toDateString();

        $query->whereBetween('scheduled_at', [$from . ' 00:00:00', $to . ' 23:59:59']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get();
    }

    public function getUpcomingSessions(?int $gymId, array $filters = [])
    {
        $query = TrainingSession::forGym($gymId)
            ->upcoming()
            ->with(['trainer:id,name,email', 'member:id,name,email'])
            ->orderBy('scheduled_at');

        if (! empty($filters['trainer_id'])) {
            $query->forTrainer($filters['trainer_id']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function validateBothBelongToGym(User $trainer, User $member, ?int $gymId): void
    {
        abort_if($trainer->gym_id !== $gymId, 403, 'Trainer does not belong to this gym.');
        abort_if($member->gym_id  !== $gymId, 403, 'Member does not belong to this gym.');
    }
}
