<?php

namespace App\Providers;

use App\GymContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GymContext::class, fn () => new GymContext());
    }

    public function boot(): void
    {
        // Generous limit: Hikvision terminals replay offline events in bursts after
        // a network outage, and heartbeats alone would otherwise chew through a tight cap.
        RateLimiter::for('biometric-hook', fn (Request $r) => Limit::perMinute(300)->by(($r->route('token') ?? '') . '|' . $r->ip()));
    }
}
