<?php

namespace App\Models;

use App\Models\Concerns\HasGymScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingSession extends Model
{
    use HasFactory, HasGymScope, SoftDeletes;

    protected $fillable = [
        'gym_id', 'trainer_id', 'member_id', 'title',
        'notes', 'scheduled_at', 'duration_mins',
        'session_type', 'status',
    ];

    protected $casts = [
        'scheduled_at'  => 'datetime',
        'duration_mins' => 'integer',
    ];

    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    public function trainer()
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function member()
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function isUpcoming(): bool
    {
        return $this->scheduled_at->isFuture() && $this->status === 'scheduled';
    }

    public function isPast(): bool
    {
        return $this->scheduled_at->isPast();
    }

    // Scopes

    public function scopeForGym(Builder $query, ?int $gymId): Builder
    {
        if ($gymId === null) return $query;
        return $query->where('gym_id', $gymId);
    }

    public function scopeForTrainer(Builder $query, int $trainerId): Builder
    {
        return $query->where('trainer_id', $trainerId);
    }

    public function scopeForMember(Builder $query, int $memberId): Builder
    {
        return $query->where('member_id', $memberId);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('status', 'scheduled')
                     ->where('scheduled_at', '>=', now());
    }

    public function scopeForDateRange(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('scheduled_at', [$from, $to]);
    }
}
