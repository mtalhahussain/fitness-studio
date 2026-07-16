<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Gym extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'domain', 'subdomain', 'email', 'phone', 'address', 'city', 'country',
        'logo', 'timezone', 'currency', 'status', 'subscription_plan',
        'trial_ends_at', 'subscription_ends_at', 'settings', 'modules',
        'whatsapp_enabled', 'whatsapp_token', 'whatsapp_phone_number_id', 'whatsapp_business_account_id',
        'whatsapp_message_template', 'whatsapp_template_name', 'whatsapp_template_language',
    ];

    protected $casts = [
        'settings'             => 'array',
        'modules'              => 'array',
        'trial_ends_at'        => 'datetime',
        'subscription_ends_at' => 'datetime',
    ];

    public function hasModule(string $module): bool
    {
        $modules = $this->modules ?? config('modules.default');
        return in_array($module, $modules);
    }

    public function enabledModules(): array
    {
        return $this->modules ?? config('modules.default');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function owner()
    {
        return $this->hasOne(User::class)->whereHas('roles', fn ($q) => $q->where('name', 'owner'));
    }

    public function trainers()
    {
        return $this->hasMany(User::class)->whereHas('roles', fn ($q) => $q->where('name', 'trainer'));
    }

    public function members()
    {
        return $this->hasMany(User::class)->whereHas('roles', fn ($q) => $q->where('name', 'member'));
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
