<?php

namespace App\Models;

use App\Models\Concerns\HasGymScope;
use Illuminate\Database\Eloquent\Model;

class TrainerCommissionConfig extends Model
{
    use HasGymScope;

    protected $fillable = [
        'gym_id', 'trainer_id', 'commission_rate', 'kind', 'effective_from', 'effective_to', 'created_by',
    ];

    const KIND_BASE      = 'base';
    const KIND_TEMPORARY = 'temporary';

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'effective_from'  => 'date',
        'effective_to'    => 'date',
    ];

    public function gym()     { return $this->belongsTo(Gym::class); }
    public function trainer() { return $this->belongsTo(User::class, 'trainer_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeForGym($query, ?int $gymId)
    {
        if ($gymId === null) return $query;
        return $query->where('gym_id', $gymId);
    }

    public function scopeActiveOn($query, string $date)
    {
        return $query->whereDate('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date));
    }

    /** Temporary (date-range) overrides first, then the latest ongoing rate. */
    public function scopeByPriority($query)
    {
        return $query->orderByRaw("CASE WHEN kind = 'temporary' THEN 0 ELSE 1 END")
            ->orderBy('effective_from', 'desc')
            ->orderBy('id', 'desc');
    }

    public function statusOn(string $date): string
    {
        if ($this->effective_from->toDateString() > $date) return 'scheduled';
        if ($this->effective_to && $this->effective_to->toDateString() < $date) return 'expired';
        return 'active';
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
