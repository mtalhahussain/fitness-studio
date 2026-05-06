<?php

namespace App\Events;

use App\Models\Membership;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MembershipExpiring
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Membership $membership,
        public readonly int $daysRemaining,
    ) {}
}
