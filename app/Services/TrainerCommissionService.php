<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\MemberTrainingPeriod;
use App\Models\Payment;
use App\Models\TrainerCommission;
use App\Models\TrainerCommissionConfig;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrainerCommissionService extends BaseService
{
    const DEFAULT_COMMISSION_RATE = 50.00;

    public function getCommissionRate(?int $gymId, int $trainerId, ?string $date = null): float
    {
        $config = $this->resolveConfig($gymId, $trainerId, $date ?? now()->toDateString());

        return $config ? (float) $config->commission_rate : self::DEFAULT_COMMISSION_RATE;
    }

    /**
     * Config that applies on $date: trainer-specific beats gym default; within a scope a
     * temporary (date-range) override beats the ongoing base rate, which resumes after it ends.
     */
    public function resolveConfig(?int $gymId, ?int $trainerId, string $date): ?TrainerCommissionConfig
    {
        $find = fn (?int $tid) => TrainerCommissionConfig::where('gym_id', $gymId)
            ->where('trainer_id', $tid)
            ->activeOn($date)
            ->byPriority()
            ->first();

        return ($trainerId ? $find($trainerId) : null) ?? $find(null);
    }

    public function calculateFromPayment(Payment $payment, Invoice $invoice): ?TrainerCommission
    {
        if (!$invoice->trainer_id || !$invoice->user_id) {
            return null;
        }

        if (TrainerCommission::where('payment_id', $payment->id)->exists()) {
            return null;
        }

        $trainerId = $invoice->trainer_id;
        $memberId  = $invoice->user_id;
        $gymId     = $invoice->gym_id;
        $date      = $payment->paid_at?->toDateString() ?? now()->toDateString();

        $rate         = $this->getCommissionRate($gymId, $trainerId, $date);
        $trainerShare = round($payment->amount * ($rate / 100), 2);
        $gymShare     = round($payment->amount - $trainerShare, 2);

        $period = MemberTrainingPeriod::where('gym_id', $gymId)
            ->where('member_id', $memberId)
            ->where('trainer_id', $trainerId)
            ->where('status', 'active')
            ->first();

        return TrainerCommission::create([
            'gym_id'             => $gymId,
            'trainer_id'         => $trainerId,
            'member_id'          => $memberId,
            'payment_id'         => $payment->id,
            'invoice_id'         => $invoice->id,
            'training_period_id' => $period?->id,
            'total_amount'       => $payment->amount,
            'trainer_share'      => $trainerShare,
            'gym_share'          => $gymShare,
            'commission_rate'    => $rate,
            'period_month'       => Carbon::parse($date)->startOfMonth()->toDateString(),
            'status'             => 'pending',
        ]);
    }

    public function getTrainerEarnings(int $trainerId, ?int $gymId, ?string $month = null): array
    {
        $query = TrainerCommission::forGym($gymId)
            ->forTrainer($trainerId)
            ->active();

        if ($month) {
            $query->forMonth($month);
        }

        $commissions = $query->with(['member:id,name,email', 'invoice:id,invoice_number'])
            ->get();

        $byMember = $commissions->groupBy('member_id')->map(function ($items) {
            $total        = (float) $items->sum('total_amount');
            $trainerShare = (float) $items->sum('trainer_share');

            return [
                'member'        => $items->first()?->member,
                'total_amount'  => $total,
                'trainer_share' => $trainerShare,
                'gym_share'     => (float) $items->sum('gym_share'),
                'count'         => $items->count(),
                // Rates applied to this member's payments (several if the rate changed mid-period).
                'rates'         => $items->pluck('commission_rate')->map(fn ($r) => (float) $r)->unique()->sort()->values(),
                'effective_rate'=> $total > 0 ? round($trainerShare / $total * 100, 2) : 0.0,
            ];
        })->values();

        return [
            'total_revenue'    => (float) $commissions->sum('total_amount'),
            'lifetime_earnings'=> (float) $commissions->sum('trainer_share'),
            'gym_share'        => (float) $commissions->sum('gym_share'),
            'commission_count' => $commissions->count(),
            'by_member'        => $byMember,
        ];
    }

    public function getMonthlyBreakdown(int $trainerId, ?int $gymId, int $months = 12): Collection
    {
        return TrainerCommission::forGym($gymId)
            ->forTrainer($trainerId)
            ->active()
            ->where('period_month', '>=', now()->subMonths($months)->startOfMonth()->toDateString())
            ->selectRaw('period_month, SUM(total_amount) as total, SUM(trainer_share) as trainer_share, SUM(gym_share) as gym_share, COUNT(*) as count')
            ->groupBy('period_month')
            ->orderBy('period_month')
            ->get();
    }

    public function getGymVsTrainerSplit(?int $gymId, ?string $month = null): array
    {
        $query = TrainerCommission::forGym($gymId)->active();

        if ($month) {
            $query->forMonth($month);
        }

        $totals = $query->selectRaw('SUM(total_amount) as total, SUM(trainer_share) as trainer_total, SUM(gym_share) as gym_total')
            ->first();

        return [
            'total'         => (float) ($totals->total ?? 0),
            'trainer_total' => (float) ($totals->trainer_total ?? 0),
            'gym_total'     => (float) ($totals->gym_total ?? 0),
        ];
    }

    public function getCommissionReport(?int $gymId, ?string $month = null): Collection
    {
        $query = TrainerCommission::forGym($gymId)
            ->active()
            ->with(['trainer:id,name,email', 'member:id,name,email']);

        if ($month) {
            $query->forMonth($month);
        }

        return $query->get()->groupBy('trainer_id')->map(function ($items) {
            return [
                'trainer'       => $items->first()?->trainer,
                'total_revenue' => (float) $items->sum('total_amount'),
                'trainer_share' => (float) $items->sum('trainer_share'),
                'gym_share'     => (float) $items->sum('gym_share'),
                'count'         => $items->count(),
                'members'       => $items->pluck('member')->unique('id')->values(),
            ];
        })->values();
    }

    public function setCommissionConfig(?int $gymId, array $data): TrainerCommissionConfig
    {
        $trainerId     = $data['trainer_id'] ?? null;
        $effectiveFrom = Carbon::parse($data['effective_from'])->toDateString();
        $effectiveTo   = ! empty($data['effective_to']) ? Carbon::parse($data['effective_to'])->toDateString() : null;

        if ($effectiveTo && $effectiveTo < $effectiveFrom) {
            throw ValidationException::withMessages(['effective_to' => 'End date must be on or after the start date.']);
        }

        $scope = fn () => TrainerCommissionConfig::where('gym_id', $gymId)->where('trainer_id', $trainerId);

        return DB::transaction(function () use ($scope, $gymId, $trainerId, $effectiveFrom, $effectiveTo, $data) {
            $attrs = [
                'gym_id'          => $gymId,
                'trainer_id'      => $trainerId,
                'commission_rate' => $data['commission_rate'],
                'effective_from'  => $effectiveFrom,
                'created_by'      => auth()->id(),
            ];

            // Date-range override: leaves the ongoing rate untouched so it resumes after the range.
            if ($effectiveTo) {
                $overlap = $scope()->where('kind', TrainerCommissionConfig::KIND_TEMPORARY)
                    ->whereDate('effective_from', '<=', $effectiveTo)
                    ->whereDate('effective_to', '>=', $effectiveFrom)
                    ->first();

                if ($overlap) {
                    throw ValidationException::withMessages(['effective_from' => sprintf(
                        'Overlaps an existing temporary rate (%s%% from %s to %s).',
                        $overlap->commission_rate,
                        $overlap->effective_from->format('d M Y'),
                        $overlap->effective_to->format('d M Y'),
                    )]);
                }

                return TrainerCommissionConfig::create($attrs + [
                    'kind'         => TrainerCommissionConfig::KIND_TEMPORARY,
                    'effective_to' => $effectiveTo,
                ]);
            }

            $bases = fn () => $scope()->where('kind', TrainerCommissionConfig::KIND_BASE);

            // Same start date → correct that rate instead of stacking a duplicate.
            if ($same = $bases()->whereDate('effective_from', $effectiveFrom)->first()) {
                $same->update(['commission_rate' => $data['commission_rate'], 'created_by' => auth()->id()]);
                return $same->fresh();
            }

            // Close the ongoing rate running into the new start date (history is kept, not deleted).
            $bases()->whereDate('effective_from', '<', $effectiveFrom)
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $effectiveFrom))
                ->update(['effective_to' => Carbon::parse($effectiveFrom)->subDay()->toDateString()]);

            // Backdated: stop just before the next ongoing rate already on file.
            $next = $bases()->whereDate('effective_from', '>', $effectiveFrom)->orderBy('effective_from')->first();

            return TrainerCommissionConfig::create($attrs + [
                'kind'         => TrainerCommissionConfig::KIND_BASE,
                'effective_to' => $next?->effective_from->copy()->subDay()->toDateString(),
            ]);
        });
    }

    /** Full rate history for one trainer (or the gym default when $trainerId is null), newest first. */
    public function getConfigHistory(?int $gymId, ?int $trainerId, bool $includeGymDefault = false): Collection
    {
        $today   = now()->toDateString();
        $current = $this->resolveConfig($gymId, $trainerId, $today);

        return TrainerCommissionConfig::where('gym_id', $gymId)
            ->where(function ($q) use ($trainerId, $includeGymDefault) {
                $q->where('trainer_id', $trainerId);
                if ($includeGymDefault && $trainerId) {
                    $q->orWhereNull('trainer_id');
                }
            })
            ->with(['trainer:id,name', 'creator:id,name'])
            ->orderBy('effective_from', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->each(function (TrainerCommissionConfig $c) use ($today, $current) {
                $c->setAttribute('status', $c->statusOn($today));
                $c->setAttribute('is_current', $current?->id === $c->id);
            });
    }

    public function getConfigs(?int $gymId): array
    {
        $today      = now()->toDateString();
        $gymDefault = $this->resolveConfig($gymId, null, $today);

        $trainerConfigs = TrainerCommissionConfig::forGym($gymId)
            ->whereNotNull('trainer_id')
            ->activeOn($today)
            ->byPriority()
            ->with('trainer:id,name')
            ->get()
            ->unique('trainer_id')
            ->values();

        $history = TrainerCommissionConfig::forGym($gymId)
            ->with(['trainer:id,name', 'creator:id,name'])
            ->orderBy('effective_from', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->each(fn ($c) => $c->setAttribute('status', $c->statusOn($today)));

        return [
            'gym_default'     => $gymDefault,
            'default_rate'    => $gymDefault ? (float) $gymDefault->commission_rate : self::DEFAULT_COMMISSION_RATE,
            'trainer_configs' => $trainerConfigs,
            'history'         => $history,
        ];
    }
}
