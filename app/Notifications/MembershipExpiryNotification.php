<?php

namespace App\Notifications;

use App\Channels\WhatsAppChannel;
use App\Channels\WhatsAppMessage;
use App\Models\Membership;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MembershipExpiryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Membership $membership,
        private readonly int $daysRemaining,
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
        $plan    = $this->membership->plan?->name ?? 'Your plan';
        $expiry  = $this->membership->end_date?->format('d-M-Y') ?? 'soon';
        $days    = $this->daysRemaining;

        return (new MailMessage)
            ->subject("⚠️ Membership Expiring in {$days} day(s) — {$plan}")
            ->greeting("Hello {$notifiable->name}!")
            ->line("Your **{$plan}** membership expires in **{$days} day(s)** on {$expiry}.")
            ->line("Renew now to keep enjoying uninterrupted gym access.")
            ->action('Renew Membership', url('/'))
            ->line('Thank you for being a valued member!');
    }

    public function toWhatsApp(object $notifiable): WhatsAppMessage
    {
        $plan  = $this->membership->plan?->name ?? 'Your plan';
        $days  = $this->daysRemaining;
        $expiry = $this->membership->end_date?->format('d-M-Y') ?? 'soon';

        return WhatsAppMessage::create()
            ->to($notifiable->phone)
            ->body("Hi {$notifiable->name}! Your *{$plan}* membership expires in *{$days} day(s)* on {$expiry}. Please renew to continue your gym access.");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'           => 'membership_expiring',
            'membership_id'  => $this->membership->id,
            'days_remaining' => $this->daysRemaining,
            'end_date'       => $this->membership->end_date?->toDateString(),
        ];
    }
}
