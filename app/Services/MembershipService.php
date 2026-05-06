<?php

namespace App\Services;

use App\Events\MembershipExpired;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MembershipService
{
    /**
     * Create a new member user and assign an initial membership.
     */
    public function createMember(array $data, ?int $gymId): User
    {
        return DB::transaction(function () use ($data, $gymId) {
            $member = User::create([
                'gym_id'   => $gymId,
                'name'     => $data['name'],
                'email'    => $data['email'],
                'phone'    => $data['phone'] ?? null,
                'password' => bcrypt($data['password'] ?? str()->random(12)),
                'status'   => 'active',
            ]);

            $member->assignRole('member');

            if (! empty($data['plan_id'])) {
                $this->assignMembership($member, $data['plan_id'], $gymId, $data);
            }

            return $member->load('memberships.plan');
        });
    }

    /**
     * Assign (or renew) a membership plan to a member.
     */
    public function assignMembership(User $member, int $planId, ?int $gymId, array $data = []): Membership
    {
        $plan = MembershipPlan::active()->forGym($gymId)->findOrFail($planId);

        $startDate = isset($data['start_date'])
            ? Carbon::parse($data['start_date'])
            : now()->startOfDay();

        $endDate = $this->calculateEndDate($plan, $startDate);

        return Membership::create([
            'gym_id'      => $gymId,
            'user_id'     => $member->id,
            'plan_id'     => $plan->id,
            'start_date'  => $startDate,
            'end_date'    => $endDate,
            'status'      => 'active',
            'amount_paid' => $data['amount_paid'] ?? $plan->price,
            'notes'       => $data['notes'] ?? null,
        ]);
    }

    /**
     * Renew an existing membership from today (or after current end_date if still active).
     */
    public function renewMembership(Membership $membership, array $data = []): Membership
    {
        $plan = $membership->plan;

        // Renew from end of current period if still active, else from today
        $startDate = $membership->isExpired()
            ? now()->startOfDay()
            : $membership->end_date->addDay()->startOfDay();

        $endDate = $this->calculateEndDate($plan, $startDate);

        return Membership::create([
            'gym_id'      => $membership->gym_id,
            'user_id'     => $membership->user_id,
            'plan_id'     => $plan->id,
            'start_date'  => $startDate,
            'end_date'    => $endDate,
            'status'      => 'active',
            'amount_paid' => $data['amount_paid'] ?? $plan->price,
            'notes'       => $data['notes'] ?? null,
        ]);
    }

    /**
     * Cancel a membership.
     */
    public function cancelMembership(Membership $membership): Membership
    {
        $membership->update(['status' => 'cancelled']);
        return $membership;
    }

    /**
     * Mark all memberships whose end_date has passed as expired.
     * Called by scheduled command.
     */
    public function markExpired(): int
    {
        $expiring = Membership::with('user')
            ->where('status', 'active')
            ->where('end_date', '<', now()->toDateString())
            ->get();

        if ($expiring->isEmpty()) {
            return 0;
        }

        $ids = $expiring->pluck('id')->all();
        Membership::whereIn('id', $ids)->update(['status' => 'expired']);

        foreach ($expiring as $membership) {
            MembershipExpired::dispatch($membership->setRelations($membership->getRelations()));
        }

        return count($ids);
    }

    /**
     * Calculate end_date based on plan type using Carbon for accuracy.
     */
    private function calculateEndDate(MembershipPlan $plan, Carbon $startDate): Carbon
    {
        return match ($plan->type) {
            'monthly'   => (clone $startDate)->addMonth()->subDay(),
            'quarterly' => (clone $startDate)->addMonths(3)->subDay(),
            'yearly'    => (clone $startDate)->addYear()->subDay(),
            default     => (clone $startDate)->addDays($plan->duration_days - 1),
        };
    }

    /**
     * Get paginated members for a gym with their active membership.
     */
    public function getMembers(?int $gymId, array $filters = [])
    {
        $query = User::members()
            ->forGym($gymId)
            ->with(['activeMembership.plan'])
            ->withCount('memberships');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
            );
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest()->paginate($filters['per_page'] ?? 15);
    }
}
