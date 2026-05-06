<?php

namespace App\Listeners;

use App\Events\MemberCheckedIn;
use App\Notifications\AttendanceNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendAttendanceAlert implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';
    public int    $tries = 3;
    public int    $backoff = 60;

    public function handle(MemberCheckedIn $event): void
    {
        $attendance = $event->attendance;

        $user = $attendance->user ?? $attendance->load('user')->user;

        if (! $user) {
            return;
        }

        $user->notify(new AttendanceNotification($attendance));
    }
}
