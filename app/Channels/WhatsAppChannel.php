<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp notification channel.
 *
 * Current state: STUB — logs the outgoing message.
 * No API calls are made until a provider is configured.
 *
 * ─── Integration options (choose one when ready) ───────────────────────────
 *
 * 1. Twilio WhatsApp API
 *    composer require twilio/sdk
 *    Config: TWILIO_SID, TWILIO_TOKEN, TWILIO_WHATSAPP_FROM
 *
 *    $twilio = new \Twilio\Rest\Client(config('services.twilio.sid'), config('services.twilio.token'));
 *    $twilio->messages->create("whatsapp:{$message->to}", [
 *        'from' => 'whatsapp:' . config('services.twilio.whatsapp_from'),
 *        'body' => $message->body,
 *    ]);
 *
 * 2. Meta Business Cloud API (official)
 *    Config: WHATSAPP_TOKEN, WHATSAPP_PHONE_NUMBER_ID
 *
 *    Http::withToken(config('services.whatsapp.token'))
 *        ->post("https://graph.facebook.com/v18.0/" . config('services.whatsapp.phone_id') . "/messages", [
 *            'messaging_product' => 'whatsapp',
 *            'to'                => $message->to,
 *            'type'              => 'text',
 *            'text'              => ['body' => $message->body],
 *        ]);
 *
 * 3. UltraMsg / WA-Gateway (Pakistan popular choice)
 *    Config: ULTRAMSG_INSTANCE_ID, ULTRAMSG_TOKEN
 *
 *    Http::post("https://api.ultramsg.com/{$instanceId}/messages/chat", [
 *        'token'   => $token,
 *        'to'      => $message->to,
 *        'body'    => $message->body,
 *    ]);
 *
 * ───────────────────────────────────────────────────────────────────────────
 */
class WhatsAppChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        /** @var WhatsAppMessage $message */
        $message = $notification->toWhatsApp($notifiable);

        if (empty($message->to)) {
            return;
        }

        // ── TODO: replace this stub with your chosen provider ────────────────
        Log::channel('stack')->info('WhatsApp [STUB] → would send message', [
            'to'   => $message->to,
            'body' => $message->body,
        ]);
        // ────────────────────────────────────────────────────────────────────
    }
}
