<?php

namespace App\Notifications;

use App\Channels\WhatsAppChannel;
use App\Channels\WhatsAppMessage;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Payment $payment,
        private readonly Invoice $invoice,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['mail'];

        if (! empty($notifiable->phone)) {
            $channels[] = WhatsAppChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount  = 'PKR ' . number_format((float) $this->payment->amount, 2);
        $method  = ucfirst(str_replace('_', ' ', $this->payment->method));
        $invNo   = $this->invoice->invoice_number;
        $date    = $this->payment->paid_at?->format('d-M-Y, h:i A') ?? now()->format('d M Y');

        return (new MailMessage)
            ->subject("✅ Payment Received — {$invNo}")
            ->greeting("Hello {$notifiable->name}!")
            ->line("We have received your payment. Here are the details:")
            ->line("**Invoice:** {$invNo}")
            ->line("**Amount:** {$amount}")
            ->line("**Method:** {$method}")
            ->line("**Date:** {$date}")
            ->action('View Invoice', url('/pos'))
            ->line('Thank you for your payment!');
    }

    public function toWhatsApp(object $notifiable): WhatsAppMessage
    {
        $amount = 'PKR ' . number_format((float) $this->payment->amount, 2);
        $invNo  = $this->invoice->invoice_number;

        return WhatsAppMessage::create()
            ->to($notifiable->phone)
            ->body("✅ Payment confirmed! {$amount} received for invoice *{$invNo}*. Thank you, {$notifiable->name}!");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'           => 'payment_received',
            'payment_id'     => $this->payment->id,
            'invoice_id'     => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'amount'         => $this->payment->amount,
            'method'         => $this->payment->method,
        ];
    }
}
