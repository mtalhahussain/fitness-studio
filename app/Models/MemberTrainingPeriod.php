<?php

namespace App\Models;

use App\Models\Concerns\HasGymScope;
use Illuminate\Database\Eloquent\Model;

class MemberTrainingPeriod extends Model
{
    use HasGymScope;

    protected $fillable = [
        'gym_id', 'member_id', 'trainer_id', 'start_date', 'end_date', 'status', 'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function gym()         { return $this->belongsTo(Gym::class); }
    public function member()      { return $this->belongsTo(User::class, 'member_id'); }
    public function trainer()     { return $this->belongsTo(User::class, 'trainer_id'); }
    public function commissions() { return $this->hasMany(TrainerCommission::class, 'training_period_id'); }

    public function scopeForGym($query, ?int $gymId)
    {
        if ($gymId === null) return $query;
        return $query->where('gym_id', $gymId);
    }

    public function scopeActive($query)  { return $query->where('status', 'active'); }
    public function scopePaused($query)  { return $query->where('status', 'paused'); }
    public function scopeEnded($query)   { return $query->where('status', 'ended'); }

    public function scopeForMember($query, int $memberId)  { return $query->where('member_id', $memberId); }
    public function scopeForTrainer($query, int $trainerId){ return $query->where('trainer_id', $trainerId); }

    public function isActive(): bool { return $this->status === 'active'; }
    public function isPaused(): bool { return $this->status === 'paused'; }
    public function isEnded(): bool  { return $this->status === 'ended'; }
}
