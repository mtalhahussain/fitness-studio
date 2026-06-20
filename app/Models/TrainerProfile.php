<?php

namespace App\Models;

use App\Models\Concerns\HasGymScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainerProfile extends Model
{
    use HasFactory, HasGymScope;

    protected $fillable = [
        'user_id', 'gym_id', 'specialization', 'bio',
        'experience_years', 'certifications', 'hourly_rate', 'compensation_mode',
        'base_salary', 'commission_enabled', 'salary_enabled', 'is_active',
    ];

    protected $casts = [
        'certifications'   => 'array',
        'hourly_rate'      => 'decimal:2',
        'base_salary'      => 'decimal:2',
        'commission_enabled' => 'boolean',
        'salary_enabled'   => 'boolean',
        'is_active'        => 'boolean',
        'experience_years' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    public function scopeForGym($query, ?int $gymId)
    {
        if ($gymId === null) return $query;
        return $query->where('gym_id', $gymId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
