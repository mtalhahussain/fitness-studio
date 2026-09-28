<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BiometricDevice extends Model
{
    protected $fillable = [
        'gym_id', 'brand', 'serial_number', 'name', 'model',
        'location', 'api_key', 'webhook_token', 'settings',
        'is_active', 'last_seen_at',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'last_seen_at'    => 'datetime',
        'last_payload_at' => 'datetime',
        'settings'        => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $device) {
            $device->brand         ??= 'zkteco';
            $device->webhook_token ??= Str::random(40);
        });
    }

    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    public static function generateApiKey(): string
    {
        return Str::random(40);
    }

    public function regenerateWebhookToken(): void
    {
        $this->forceFill(['webhook_token' => Str::random(40)])->save();
    }

    /** Timezone the machine's clock runs in. */
    public function timezone(): string
    {
        return $this->settings['timezone'] ?? config('biometric.timezone');
    }

    /** Keep a copy of the last raw request (capped at 10 KB) for the Setup / Test panel. */
    public function storePayload(string $raw): void
    {
        $this->forceFill([
            'last_payload'    => substr($raw, 0, 10240),
            'last_payload_at' => now(),
        ])->saveQuietly();
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

    /** A device counts as online if it talked to us within this many minutes. */
    public const ONLINE_MINUTES = 5;

    /** Record contact from the machine. Heartbeats arrive every few seconds, so skip writes within 30s. */
    public function markSeen(): void
    {
        if ($this->last_seen_at && $this->last_seen_at->gt(now()->subSeconds(30))) {
            return;
        }

        $this->forceFill(['last_seen_at' => now()])->saveQuietly();
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->gt(now()->subMinutes(self::ONLINE_MINUTES));
    }

    /** online | offline | never | disabled (inactive but still trying to connect) */
    public function connectionState(): string
    {
        if (! $this->is_active) {
            return $this->isOnline() ? 'disabled' : 'offline';
        }

        if ($this->last_seen_at === null) {
            return 'never';
        }

        return $this->isOnline() ? 'online' : 'offline';
    }
}
