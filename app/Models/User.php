<?php

namespace App\Models;

use App\Models\Concerns\HasGymScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasGymScope, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'gym_id', 'name', 'email', 'phone', 'avatar', 'password',
        'status', 'date_of_birth', 'gender', 'address', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'date_of_birth'     => 'date',
            'password'          => 'hashed',
        ];
    }

    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isOwner(): bool
    {
        return $this->hasRole('owner');
    }

    public function isTrainer(): bool
    {
        return $this->hasRole('trainer');
    }

    public function isMember(): bool
    {
        return $this->hasRole('member');
    }

    // ── Membership ───────────────────────────────────────────────────────────

    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }

    public function activeMembership()
    {
        return $this->hasOne(Membership::class)->active()->latest('start_date');
    }

    // ── Trainer relations ─────────────────────────────────────────────────────

    public function trainerProfile()
    {
        return $this->hasOne(TrainerProfile::class);
    }

    /** Members assigned to this trainer */
    public function assignedMembers()
    {
        return $this->belongsToMany(
            User::class,
            'trainer_member',
            'trainer_id',
            'member_id'
        )->withPivot(['notes', 'is_active', 'assigned_at', 'unassigned_at'])
         ->wherePivot('is_active', true);
    }

    /** Trainers assigned to this member */
    public function assignedTrainers()
    {
        return $this->belongsToMany(
            User::class,
            'trainer_member',
            'member_id',
            'trainer_id'
        )->withPivot(['notes', 'is_active', 'assigned_at'])
         ->wherePivot('is_active', true);
    }

    public function trainingSessions()
    {
        return $this->hasMany(TrainingSession::class, 'trainer_id');
    }

    public function memberSessions()
    {
        return $this->hasMany(TrainingSession::class, 'member_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForGym($query, ?int $gymId)
    {
        if ($gymId === null) return $query;
        return $query->where('gym_id', $gymId);
    }

    public function scopeMembers($query)
    {
        return $query->whereHas('roles', fn ($q) => $q->where('name', 'member'));
    }

    public function scopeTrainers($query)
    {
        return $query->whereHas('roles', fn ($q) => $q->where('name', 'trainer'));
    }
}
