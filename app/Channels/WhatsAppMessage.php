<?php

namespace App\Channels;

/**
 * Fluent message builder for WhatsApp notifications.
 *
 * Usage:
 *   WhatsAppMessage::create()
 *       ->to($user->phone)
 *       ->body('Hello!')
 *       ->template('greeting', ['name' => 'John']);  // optional — for WA template messages
 */
class WhatsAppMessage
{
    public string  $to       = '';
    public string  $body     = '';
    public ?string $template = null;
    public array   $params   = [];

    public static function create(): static
    {
        return new static();
    }

    public function to(string $phone): static
    {
        // Normalize to international format without leading +
        $this->to = ltrim(preg_replace('/[^0-9+]/', '', $phone), '+');

        return $this;
    }

    public function body(string $text): static
    {
        $this->body = $text;

        return $this;
    }

    /**
     * For future Meta-approved template messages (required for 24h+ re-engagement).
     */
    public function template(string $name, array $params = []): static
    {
        $this->template = $name;
        $this->params   = $params;

        return $this;
    }
}
