<?php

namespace App\Services;

use App\Events\MembershipExpired;
use App\Events\PaymentReceived;
use App\Models\Invoice;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MembershipService extends BaseService
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

        $startDate  = isset($data['start_date']) ? Carbon::parse($data['start_date']) : now()->startOfDay();
        $endDate    = $this->calculateEndDate($plan, $startDate);
        $amountPaid = (float) ($data['amount_paid'] ?? $plan->price);

        return DB::transaction(function () use ($member, $plan, $gymId, $startDate, $endDate, $amountPaid, $data) {
            $invoice = $this->createMembershipInvoice($member->id, $plan, $gymId, $startDate, $endDate, $amountPaid);

            return Membership::create([
                'gym_id'      => $gymId,
                'user_id'     => $member->id,
                'plan_id'     => $plan->id,
                'start_date'  => $startDate,
                'end_date'    => $endDate,
                'status'      => 'active',
                'amount_paid' => $amountPaid,
                'notes'       => $data['notes'] ?? null,
                'invoice_id'  => $invoice->id,
            ]);
        });
    }

    /**
     * Renew an existing membership from today (or after current end_date if still active).
     */
    public function renewMembership(Membership $membership, array $data = []): Membership
    {
        $plan = $membership->plan;

        $startDate  = $membership->isExpired()
            ? now()->startOfDay()
            : $membership->end_date->addDay()->startOfDay();
        $endDate    = $this->calculateEndDate($plan, $startDate);
        $amountPaid = (float) ($data['amount_paid'] ?? $plan->price);

        return DB::transaction(function () use ($membership, $plan, $startDate, $endDate, $amountPaid, $data) {
            $invoice = $this->createMembershipInvoice($membership->user_id, $plan, $membership->gym_id, $startDate, $endDate, $amountPaid);

            return Membership::create([
                'gym_id'      => $membership->gym_id,
                'user_id'     => $membership->user_id,
                'plan_id'     => $plan->id,
                'start_date'  => $startDate,
                'end_date'    => $endDate,
                'status'      => 'active',
                'amount_paid' => $amountPaid,
                'notes'       => $data['notes'] ?? null,
                'invoice_id'  => $invoice->id,
            ]);
        });
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

    private function createMembershipInvoice(int $userId, MembershipPlan $plan, ?int $gymId, Carbon $startDate, Carbon $endDate, float $amountPaid): Invoice
    {
        $prefix = 'INV-' . now()->format('Ym');
        $count  = Invoice::when($gymId, fn ($q) => $q->where('gym_id', $gymId))
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->withTrashed()->count() + 1;

        $invoice = Invoice::create([
            'gym_id'          => $gymId,
            'user_id'         => $userId,
            'invoice_number'  => $prefix . '-' . str_pad($count, 4, '0', STR_PAD_LEFT),
            'subtotal'        => $plan->price,
            'tax_amount'      => 0,
            'discount_amount' => 0,
            'total_amount'    => $plan->price,
            'status'          => 'unpaid',
            'notes'           => "Membership: {$plan->name} | {$startDate->format('d-M-Y')} → {$endDate->format('d-M-Y')}",
            'due_date'        => $startDate->toDateString(),
        ]);

        $invoice->items()->create([
            'item_type'  => 'plan',
            'item_id'    => $plan->id,
            'name'       => $plan->name . ' (' . ucfirst($plan->type) . ')',
            'unit_price' => $plan->price,
            'quantity'   => 1,
            'subtotal'   => $plan->price,
        ]);

        if ($amountPaid > 0) {
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'gym_id'     => $gymId,
                'amount'     => min($amountPaid, (float) $plan->price),
                'method'     => 'cash',
                'paid_at'    => now(),
            ]);

            $totalPaid = (float) $invoice->payments()->sum('amount');
            $status    = $totalPaid >= (float) $plan->price ? 'paid' : 'partially_paid';
            $invoice->update([
                'status'  => $status,
                'paid_at' => $status === 'paid' ? now() : null,
            ]);

            PaymentReceived::dispatch($payment, $invoice->fresh());
        }

        return $invoice;
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
            ->with([
                'activeMembership.plan',
                'activeMembership.invoice' => fn ($q) => $q->withSum('payments', 'amount'),
            ])
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
