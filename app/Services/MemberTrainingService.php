<?php

namespace App\Services;

use App\Models\MemberTrainingPeriod;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MemberTrainingService extends BaseService
{
    public function startTraining(User $member, User $trainer, ?int $gymId, array $data = []): MemberTrainingPeriod
    {
        $existing = MemberTrainingPeriod::forGym($gymId)
            ->forMember($member->id)
            ->active()
            ->first();

        if ($existing) {
            if ($existing->trainer_id === $trainer->id) {
                throw new \RuntimeException('Member already has an active training period with this trainer.', 409);
            }
            $existing->update(['end_date' => now()->toDateString(), 'status' => 'ended']);
        }

        return MemberTrainingPeriod::create([
            'gym_id'     => $gymId,
            'member_id'  => $member->id,
            'trainer_id' => $trainer->id,
            'start_date' => $data['start_date'] ?? now()->toDateString(),
            'status'     => 'active',
            'notes'      => $data['notes'] ?? null,
        ]);
    }

    public function pauseTraining(User $member, ?int $gymId, array $data = []): MemberTrainingPeriod
    {
        $period = MemberTrainingPeriod::forGym($gymId)
            ->forMember($member->id)
            ->active()
            ->firstOrFail();

        $period->update([
            'end_date' => $data['pause_date'] ?? now()->toDateString(),
            'status'   => 'paused',
            'notes'    => $data['notes'] ?? $period->notes,
        ]);

        return $period->fresh();
    }

    public function resumeTraining(User $member, ?int $gymId, array $data = []): MemberTrainingPeriod
    {
        $paused = MemberTrainingPeriod::forGym($gymId)
            ->forMember($member->id)
            ->paused()
            ->latest('end_date')
            ->firstOrFail();

        $trainerId = $data['trainer_id'] ?? $paused->trainer_id;

        return MemberTrainingPeriod::create([
            'gym_id'     => $gymId,
            'member_id'  => $member->id,
            'trainer_id' => $trainerId,
            'start_date' => $data['resume_date'] ?? now()->toDateString(),
            'status'     => 'active',
            'notes'      => $data['notes'] ?? null,
        ]);
    }

    public function endTraining(User $member, ?int $gymId, array $data = []): MemberTrainingPeriod
    {
        $period = MemberTrainingPeriod::forGym($gymId)
            ->forMember($member->id)
            ->where(fn ($q) => $q->where('status', 'active')->orWhere('status', 'paused'))
            ->latest('start_date')
            ->firstOrFail();

        $period->update([
            'end_date' => $data['end_date'] ?? now()->toDateString(),
            'status'   => 'ended',
        ]);

        return $period->fresh();
    }

    public function switchTrainer(User $member, User $newTrainer, ?int $gymId, array $data = []): MemberTrainingPeriod
    {
        return DB::transaction(function () use ($member, $newTrainer, $gymId, $data) {
            $current = MemberTrainingPeriod::forGym($gymId)
                ->forMember($member->id)
                ->active()
                ->first();

            if ($current) {
                $current->update(['end_date' => now()->toDateString(), 'status' => 'ended']);
            }

            return MemberTrainingPeriod::create([
                'gym_id'     => $gymId,
                'member_id'  => $member->id,
                'trainer_id' => $newTrainer->id,
                'start_date' => $data['start_date'] ?? now()->toDateString(),
                'status'     => 'active',
                'notes'      => $data['notes'] ?? null,
            ]);
        });
    }

    public function getActivePeriod(User $member, ?int $gymId): ?MemberTrainingPeriod
    {
        return MemberTrainingPeriod::forGym($gymId)
            ->forMember($member->id)
            ->active()
            ->with('trainer:id,name,email')
            ->first();
    }

    public function getTrainingHistory(User $member, ?int $gymId)
    {
        return MemberTrainingPeriod::forGym($gymId)
            ->forMember($member->id)
            ->with(['trainer:id,name,email'])
            ->orderBy('start_date', 'desc')
            ->get();
    }

    public function getMembersByTrainer(User $trainer, ?int $gymId, ?string $status = null)
    {
        $query = MemberTrainingPeriod::forGym($gymId)
            ->forTrainer($trainer->id)
            ->with(['member:id,name,email,phone'])
            ->orderBy('start_date', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    public function getTrainerMemberSummary(User $trainer, ?int $gymId): array
    {
        $periods = MemberTrainingPeriod::forGym($gymId)
            ->forTrainer($trainer->id)
            ->get(['member_id', 'status']);

        $activeMembers   = $periods->where('status', 'active')->pluck('member_id')->unique();
        $pausedMembers   = $periods->where('status', 'paused')->pluck('member_id')->unique();
        $allMemberIds    = $periods->pluck('member_id')->unique();
        $inactiveMembers = $allMemberIds->diff($activeMembers)->diff($pausedMembers);

        return [
            'total_members'    => $allMemberIds->count(),
            'active_members'   => $activeMembers->count(),
            'paused_members'   => $pausedMembers->count(),
            'inactive_members' => $inactiveMembers->count(),
        ];
    }
}
