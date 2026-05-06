<?php

namespace App\Notifications;

use App\Channels\WhatsAppChannel;
use App\Channels\WhatsAppMessage;
use App\Models\Attendance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AttendanceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Attendance $attendance,
    ) {}

    public function via(object $notifiable): array
    {
        // Email for check-in may be too frequent — keep mail off by default.
        // Enable if needed; WhatsApp is the primary channel for real-time alerts.
        $channels = [];

        if (! empty($notifiable->phone)) {
            $channels[] = WhatsAppChannel::class;
        }

        // Uncomment to also send email:
        // $channels[] = 'mail';

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $time = $this->attendance->check_in_time?->format('h:i A') ?? now()->format('h:i A');
        $date = $this->attendance->check_in_time?->format('d M Y') ?? now()->format('d M Y');

        return (new MailMessage)
            ->subject("✅ Check-in Confirmed — {$time}")
            ->greeting("Welcome, {$notifiable->name}!")
            ->line("You have successfully checked in.")
            ->line("**Date:** {$date}")
            ->line("**Time:** {$time}")
            ->line('Have a great workout!');
    }

    public function toWhatsApp(object $notifiable): WhatsAppMessage
    {
        $time = $this->attendance->check_in_time?->format('h:i A') ?? now()->format('h:i A');

        return WhatsAppMessage::create()
            ->to($notifiable->phone)
            ->body("✅ Welcome {$notifiable->name}! You checked in at *{$time}*. Have a great workout! 💪");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'          => 'member_checked_in',
            'attendance_id' => $this->attendance->id,
            'check_in_time' => $this->attendance->check_in_time?->toIso8601String(),
        ];
    }
}
