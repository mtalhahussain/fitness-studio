<?php

namespace App\Listeners;

use App\Events\MembershipExpired;
use App\Notifications\MembershipExpiredNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendMembershipExpiredAlert implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';
    public int    $tries = 3;
    public int    $backoff = 60;

    public function handle(MembershipExpired $event): void
    {
        $membership = $event->membership;

        $user = $membership->user ?? $membership->load('user')->user;

        if (! $user) {
            return;
        }

        $user->notify(new MembershipExpiredNotification($membership));
    }
}
