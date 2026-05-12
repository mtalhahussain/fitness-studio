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

class TrainerCommissionService extends BaseService
{
    const DEFAULT_COMMISSION_RATE = 50.00;

    public function getCommissionRate(?int $gymId, int $trainerId, ?string $date = null): float
    {
        $date = $date ?? now()->toDateString();

        $trainerConfig = TrainerCommissionConfig::where('gym_id', $gymId)
            ->where('trainer_id', $trainerId)
            ->activeOn($date)
            ->orderBy('effective_from', 'desc')
            ->first();

        if ($trainerConfig) {
            return (float) $trainerConfig->commission_rate;
        }

        $gymDefault = TrainerCommissionConfig::where('gym_id', $gymId)
            ->whereNull('trainer_id')
            ->activeOn($date)
            ->orderBy('effective_from', 'desc')
            ->first();

        return $gymDefault ? (float) $gymDefault->commission_rate : self::DEFAULT_COMMISSION_RATE;
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
            return [
                'member'        => $items->first()?->member,
                'total_amount'  => (float) $items->sum('total_amount'),
                'trainer_share' => (float) $items->sum('trainer_share'),
                'gym_share'     => (float) $items->sum('gym_share'),
                'count'         => $items->count(),
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
        $trainerId    = $data['trainer_id'] ?? null;
        $effectiveFrom = $data['effective_from'];

        TrainerCommissionConfig::where('gym_id', $gymId)
            ->where('trainer_id', $trainerId)
            ->whereNull('effective_to')
            ->where('effective_from', '<', $effectiveFrom)
            ->update(['effective_to' => Carbon::parse($effectiveFrom)->subDay()->toDateString()]);

        return TrainerCommissionConfig::create([
            'gym_id'          => $gymId,
            'trainer_id'      => $trainerId,
            'commission_rate' => $data['commission_rate'],
            'effective_from'  => $effectiveFrom,
            'effective_to'    => $data['effective_to'] ?? null,
        ]);
    }

    public function getConfigs(?int $gymId): array
    {
        $gymDefault = TrainerCommissionConfig::forGym($gymId)
            ->gymDefault()
            ->orderBy('effective_from', 'desc')
            ->first();

        $trainerConfigs = TrainerCommissionConfig::forGym($gymId)
            ->whereNotNull('trainer_id')
            ->with('trainer:id,name')
            ->orderBy('effective_from', 'desc')
            ->get()
            ->groupBy('trainer_id')
            ->map(fn ($items) => $items->first());

        return [
            'gym_default'    => $gymDefault,
            'default_rate'   => $gymDefault ? (float) $gymDefault->commission_rate : self::DEFAULT_COMMISSION_RATE,
            'trainer_configs' => $trainerConfigs->values(),
        ];
    }
}
