<?php

namespace App\Console\Commands;

use App\Services\MembershipService;
use Illuminate\Console\Command;

class UpdateExpiredMemberships extends Command
{
    protected $signature   = 'memberships:expire';
    protected $description = 'Mark memberships as expired when their end_date has passed';

    public function handle(MembershipService $service): void
    {
        $count = $service->markExpired();
        $this->info("Marked {$count} membership(s) as expired.");
    }
}
