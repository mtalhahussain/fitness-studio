<?php

namespace App\Notifications;

use App\Channels\WhatsAppChannel;
use App\Channels\WhatsAppMessage;
use App\Models\Membership;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MembershipExpiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Membership $membership,
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
        $plan = $this->membership->plan?->name ?? 'Your plan';

        return (new MailMessage)
            ->subject("❌ Membership Expired — {$plan}")
            ->greeting("Hello {$notifiable->name}!")
            ->line("Your **{$plan}** membership has expired.")
            ->line("Renew today to regain full gym access without losing your history.")
            ->action('Renew Now', url('/'))
            ->line('We hope to see you back soon!');
    }

    public function toWhatsApp(object $notifiable): WhatsAppMessage
    {
        $plan = $this->membership->plan?->name ?? 'Your plan';

        return WhatsAppMessage::create()
            ->to($notifiable->phone)
            ->body("Hi {$notifiable->name}! Your *{$plan}* membership has expired. Renew now to continue your gym access.");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'          => 'membership_expired',
            'membership_id' => $this->membership->id,
            'plan'          => $this->membership->plan?->name,
        ];
    }
}
