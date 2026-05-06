<?php

namespace App\Listeners;

use App\Events\MembershipExpiring;
use App\Notifications\MembershipExpiryNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendMembershipExpiryReminder implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';
    public int    $tries = 3;
    public int    $backoff = 60;

    public function handle(MembershipExpiring $event): void
    {
        $membership = $event->membership;

        // Ensure user relationship is loaded
        $user = $membership->user ?? $membership->load('user')->user;

        if (! $user) {
            return;
        }

        $user->notify(new MembershipExpiryNotification($membership, $event->daysRemaining));
    }
}
