<?php

namespace App\Models;

use App\Models\Concerns\HasGymScope;
use Illuminate\Database\Eloquent\Model;

class TrainerCommissionConfig extends Model
{
    use HasGymScope;

    protected $fillable = [
        'gym_id', 'trainer_id', 'commission_rate', 'effective_from', 'effective_to',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'effective_from'  => 'date',
        'effective_to'    => 'date',
    ];

    public function gym()     { return $this->belongsTo(Gym::class); }
    public function trainer() { return $this->belongsTo(User::class, 'trainer_id'); }

    public function scopeForGym($query, ?int $gymId)
    {
        if ($gymId === null) return $query;
        return $query->where('gym_id', $gymId);
    }

    public function scopeActiveOn($query, string $date)
    {
        return $query->where('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date));
    }

    public function scopeForTrainer($query, ?int $trainerId)
    {
        return $query->where('trainer_id', $trainerId);
    }

    public function scopeGymDefault($query)
    {
        return $query->whereNull('trainer_id');
    }
}
