<?php

namespace App\Providers;

use App\GymContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GymContext::class, fn () => new GymContext());
    }

    public function boot(): void {}
}
