<?php

namespace App\Services;

use App\Events\MemberCheckedIn;
use App\Events\MembershipExpired;
use App\Events\MembershipExpiring;
use App\Events\PaymentReceived;
use App\Models\Attendance;
use App\Models\Invoice;
use App\Models\Membership;
use App\Models\Payment;

class NotificationService
{
    public function notifyMembershipExpiring(Membership $membership, int $daysRemaining): void
    {
        MembershipExpiring::dispatch($membership, $daysRemaining);
    }

    public function notifyMembershipExpired(Membership $membership): void
    {
        MembershipExpired::dispatch($membership);
    }

    public function notifyPaymentReceived(Payment $payment, Invoice $invoice): void
    {
        PaymentReceived::dispatch($payment, $invoice);
    }

    public function notifyMemberCheckedIn(Attendance $attendance): void
    {
        MemberCheckedIn::dispatch($attendance);
    }
}
