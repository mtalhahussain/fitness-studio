<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppService
{
    /**
     * Send template message via WhatsApp Cloud API.
     *
     * Example success response shape (Meta):
     * [
     *   "messaging_product" => "whatsapp",
     *   "contacts" => [["input" => "923001112233", "wa_id" => "923001112233"]],
     *   "messages" => [["id" => "wamid.HBgM..." ]]
     * ]
     *
     * Example error response shape (Meta):
     * [
     *   "error" => [
     *     "message" => "(#131047) Re-engagement message",
     *     "type" => "OAuthException",
     *     "code" => 131047
     *   ]
     * ]
     */
    public function sendTemplate(string $to, string $templateName, array $components = [], string $language = 'en_US'): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->normalizePhone($to),
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $language,
                ],
            ],
        ];

        if (! empty($components)) {
            $payload['template']['components'] = $components;
        }

        $response = $this->request($payload);

        return [
            'status_code' => $response->status(),
            'data' => $response->json(),
        ];
    }

    private function request(array $payload): Response
    {
        $token = (string) config('whatsapp.api.token');
        $phoneNumberId = (string) config('whatsapp.api.phone_number_id');
        $version = (string) config('whatsapp.api.version', 'v20.0');

        if ($token === '' || $phoneNumberId === '') {
            throw new RuntimeException('WhatsApp Cloud API credentials are missing. Set WHATSAPP_TOKEN and WHATSAPP_PHONE_NUMBER_ID.');
        }

        $url = "https://graph.facebook.com/{$version}/{$phoneNumberId}/messages";

        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->post($url, $payload);

        if (! $response->successful()) {
            throw new RuntimeException('WhatsApp API request failed: ' . $response->body());
        }

        return $response;
    }

    private function normalizePhone(string $phone): string
    {
        return ltrim((string) preg_replace('/[^0-9+]/', '', $phone), '+');
    }
}

