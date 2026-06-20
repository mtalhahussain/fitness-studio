<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reminders:payment-due')->dailyAt('09:00')->withoutOverlapping();

Schedule::command('memberships:expire')->dailyAt('00:00');

Schedule::command('memberships:reminders')->dailyAt('09:00');