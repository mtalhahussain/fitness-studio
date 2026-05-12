<?php

namespace App\Models;

use App\Models\Concerns\HasGymScope;
use Illuminate\Database\Eloquent\Model;

class TrainerCommission extends Model
{
    use HasGymScope;

    protected $fillable = [
        'gym_id', 'trainer_id', 'member_id', 'payment_id', 'invoice_id',
        'training_period_id', 'total_amount', 'trainer_share', 'gym_share',
        'commission_rate', 'period_month', 'status', 'paid_at', 'notes',
    ];

    protected $casts = [
        'total_amount'    => 'decimal:2',
        'trainer_share'   => 'decimal:2',
        'gym_share'       => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'period_month'    => 'date',
        'paid_at'         => 'datetime',
    ];

    public function gym()            { return $this->belongsTo(Gym::class); }
    public function trainer()        { return $this->belongsTo(User::class, 'trainer_id'); }
    public function member()         { return $this->belongsTo(User::class, 'member_id'); }
    public function payment()        { return $this->belongsTo(Payment::class); }
    public function invoice()        { return $this->belongsTo(Invoice::class); }
    public function trainingPeriod() { return $this->belongsTo(MemberTrainingPeriod::class, 'training_period_id'); }

    public function scopeForGym($query, ?int $gymId)
    {
        if ($gymId === null) return $query;
        return $query->where('gym_id', $gymId);
    }

    public function scopeForTrainer($query, int $trainerId) { return $query->where('trainer_id', $trainerId); }
    public function scopeForMember($query, int $memberId)   { return $query->where('member_id', $memberId); }

    public function scopeForMonth($query, string $month)
    {
        return $query->whereYear('period_month', substr($month, 0, 4))
            ->whereMonth('period_month', substr($month, 5, 2));
    }

    public function scopeActive($query)   { return $query->where('status', '!=', 'cancelled'); }
}
