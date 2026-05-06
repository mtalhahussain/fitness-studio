<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

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

    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }

    public function activeMembership()
    {
        return $this->hasOne(Membership::class)->active()->latest('start_date');
    }

    public function scopeForGym($query, int $gymId)
    {
        return $query->where('gym_id', $gymId);
    }

    public function scopeMembers($query)
    {
        return $query->whereHas('roles', fn ($q) => $q->where('name', 'member'));
    }
}
