<?php

namespace App\Console\Commands;

use App\Models\Membership;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendMembershipExpiryReminders extends Command
{
    protected $signature   = 'memberships:reminders';
    protected $description = 'Send expiry reminder notifications at 7, 3, and 1 day(s) before expiry.';

    public function handle(NotificationService $notifications): int
    {
        $thresholds = [7, 3, 1];
        $sent       = 0;

        foreach ($thresholds as $days) {
            $targetDate = now()->addDays($days)->toDateString();

            Membership::with('user')
                ->where('status', 'active')
                ->whereDate('end_date', $targetDate)
                ->chunk(100, function ($memberships) use ($notifications, $days, &$sent) {
                    foreach ($memberships as $membership) {
                        if (! $membership->user) {
                            continue;
                        }

                        $notifications->notifyMembershipExpiring($membership, $days);
                        $sent++;
                    }
                });
        }

        $this->info("Dispatched {$sent} membership expiry reminder(s).");

        return Command::SUCCESS;
    }
}
