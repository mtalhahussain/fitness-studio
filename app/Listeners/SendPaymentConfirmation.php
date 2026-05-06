<?php

namespace App\Listeners;

use App\Events\PaymentReceived;
use App\Notifications\PaymentConfirmationNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendPaymentConfirmation implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';
    public int    $tries = 3;
    public int    $backoff = 60;

    public function handle(PaymentReceived $event): void
    {
        $invoice = $event->invoice;

        $user = $invoice->user ?? $invoice->load('user')->user;

        if (! $user) {
            return;
        }

        $user->notify(new PaymentConfirmationNotification($event->payment, $invoice));
    }
}
