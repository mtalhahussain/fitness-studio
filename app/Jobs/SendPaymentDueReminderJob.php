<?php

namespace App\Jobs;

use App\Models\Gym;
use App\Models\WhatsAppMessageLog;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendPaymentDueReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(
        public int $logId,
        public string $phone,
        public string $templateName,
        public array $components = [],
        public ?int $gymId = null,
        public string $language = 'en_US',
    ) {}

    public function handle(WhatsAppService $whatsAppService): void
    {
        $log = WhatsAppMessageLog::find($this->logId);
        if (! $log) {
            return;
        }

        try {
            // Use gym-specific credentials if available, otherwise fall back to global config
            if ($this->gymId) {
                $gym = Gym::find($this->gymId);
                if (! $gym || ! $gym->whatsapp_enabled) {
                    $log->update([
                        'status' => 'failed',
                        'response' => json_encode(['error' => 'Gym WhatsApp feature is not enabled.'], JSON_UNESCAPED_UNICODE),
                        'sent_at' => now(),
                    ]);
                    return;
                }
                // Override config temporarily with gym credentials
                config([
                    'whatsapp.api.token' => $gym->whatsapp_token,
                    'whatsapp.api.phone_number_id' => $gym->whatsapp_phone_number_id,
                ]);
            }

            $result = $whatsAppService->sendTemplate(
                to: $this->phone,
                templateName: $this->templateName,
                components: $this->components,
                language: $this->language,
            );

            $ok = ($result['status_code'] ?? 0) >= 200 && ($result['status_code'] ?? 0) < 300;

            $log->update([
                'status' => $ok ? 'sent' : 'failed',
                'response' => json_encode($result['data'] ?? $result, JSON_UNESCAPED_UNICODE),
                'sent_at' => now(),
            ]);
        } catch (Throwable $e) {
            $log->update([
                'status' => 'failed',
                'response' => json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE),
                'sent_at' => now(),
            ]);

            Log::error('SendPaymentDueReminderJob execution error.', [
                'log_id' => $this->logId,
                'gym_id' => $this->gymId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        $log = WhatsAppMessageLog::find($this->logId);

        if ($log) {
            $log->update([
                'status' => 'failed',
                'response' => json_encode([
                    'error' => $exception->getMessage(),
                ], JSON_UNESCAPED_UNICODE),
                'sent_at' => now(),
            ]);
        }

        Log::error('SendPaymentDueReminderJob failed.', [
            'log_id' => $this->logId,
            'gym_id' => $this->gymId,
            'error' => $exception->getMessage(),
        ]);
    }
}

