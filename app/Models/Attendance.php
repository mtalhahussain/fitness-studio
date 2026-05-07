<?php

namespace App\Models;

use App\Models\Concerns\HasGymScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory, HasGymScope;

    protected $fillable = [
        'gym_id', 'user_id', 'check_in_time', 'check_out_time',
        'source', 'device_user_id',
    ];

    protected $casts = [
        'check_in_time'  => 'datetime',
        'check_out_time' => 'datetime',
    ];

    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isOpen(): bool
    {
        return is_null($this->check_out_time);
    }

    public function hasCheckedOut(): bool
    {
        return ! is_null($this->check_out_time);
    }

    public function duration(): ?int
    {
        if ($this->isOpen()) {
            return null;
        }

        return (int) $this->check_in_time->diffInMinutes($this->check_out_time);
    }

    public function isLateCheckout(): bool
    {
        if ($this->isOpen()) {
            return false;
        }

        return ! $this->check_in_time->isSameDay($this->check_out_time);
    }

    // Scopes

    public function scopeForGym(Builder $query, ?int $gymId): Builder
    {
        if ($gymId === null) return $query;
        return $query->where('gym_id', $gymId);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('check_out_time');
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('check_in_time', today());
    }

    public function scopeForDate(Builder $query, Carbon $date): Builder
    {
        return $query->whereDate('check_in_time', $date->toDateString());
    }

    public function scopeBiometric(Builder $query): Builder
    {
        return $query->where('source', 'biometric');
    }

    public function scopeManual(Builder $query): Builder
    {
        return $query->where('source', 'manual');
    }
}
