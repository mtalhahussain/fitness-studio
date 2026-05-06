<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MembershipPlan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'gym_id', 'name', 'type', 'duration_days',
        'price', 'description', 'features', 'is_active',
    ];

    protected $casts = [
        'features'  => 'array',
        'price'     => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Duration presets keyed by plan type
    public const DURATION_MAP = [
        'monthly'   => 30,
        'quarterly' => 90,
        'yearly'    => 365,
    ];

    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    public function memberships()
    {
        return $this->hasMany(Membership::class, 'plan_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForGym($query, int $gymId)
    {
        return $query->where('gym_id', $gymId);
    }
}
