<?php

namespace App\Providers;

use App\Events\MemberCheckedIn;
use App\Events\MembershipExpired;
use App\Events\MembershipExpiring;
use App\Events\PaymentReceived;
use App\Listeners\SendAttendanceAlert;
use App\Listeners\SendMembershipExpiredAlert;
use App\Listeners\SendMembershipExpiryReminder;
use App\Listeners\SendPaymentConfirmation;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        MembershipExpiring::class => [
            SendMembershipExpiryReminder::class,
        ],
        MembershipExpired::class => [
            SendMembershipExpiredAlert::class,
        ],
        PaymentReceived::class => [
            SendPaymentConfirmation::class,
        ],
        MemberCheckedIn::class => [
            SendAttendanceAlert::class,
        ],
    ];
}
