<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Membership extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'gym_id', 'user_id', 'plan_id', 'start_date',
        'end_date', 'status', 'amount_paid', 'notes',
    ];

    protected $casts = [
        'start_date'  => 'date',
        'end_date'    => 'date',
        'amount_paid' => 'decimal:2',
    ];

    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(MembershipPlan::class, 'plan_id');
    }

    public function isExpired(): bool
    {
        return $this->end_date->isPast() || $this->status === 'expired';
    }

    public function daysRemaining(): int
    {
        if ($this->isExpired()) {
            return 0;
        }

        return (int) now()->startOfDay()->diffInDays($this->end_date->endOfDay());
    }

    public function scopeForGym($query, ?int $gymId)
    {
        if ($gymId === null) return $query;
        return $query->where('gym_id', $gymId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('end_date', '>=', now()->toDateString());
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'active')->where('end_date', '<', now()->toDateString());
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
