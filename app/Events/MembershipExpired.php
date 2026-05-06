<?php

namespace App\Events;

use App\Models\Membership;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MembershipExpired
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Membership $membership,
    ) {}
}
