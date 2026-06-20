<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\WhatsAppMessageLog;
use App\Services\PaymentDueReminderService;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppPaymentDueReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue = 'notifications';
    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly int $invoiceId,
        public readonly int $logId,
        public readonly string $templateName = PaymentDueReminderService::TEMPLATE_PAYMENT_DUE,
    ) {}

    public function handle(WhatsAppService $whatsAppService, PaymentDueReminderService $reminderService): void
    {
        $log = WhatsAppMessageLog::find($this->logId);
        if (! $log) {
            return;
        }

        $invoice = Invoice::query()
            ->with(['user:id,name,phone'])
            ->withSum('payments', 'amount')
            ->find($this->invoiceId);

        if (! $invoice || ! $invoice->user || empty($invoice->user->phone)) {
            $log->update([
                'status' => 'skipped',
                'response' => ['reason' => 'Invoice/user/phone not found.'],
            ]);
            return;
        }

        if (! in_array($invoice->status, ['unpaid', 'partially_paid'], true)) {
            $log->update([
                'status' => 'skipped',
                'response' => ['reason' => 'Invoice is no longer unpaid/pending.'],
            ]);
            return;
        }

        try {
            $components = $reminderService->buildTemplateComponents($invoice);
            $response = $whatsAppService->sendTemplate($invoice->user->phone, $this->templateName, $components);

            $log->update([
                'status' => 'sent',
                'response' => $response,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('WhatsApp payment due reminder failed.', [
                'invoice_id' => $invoice->id,
                'user_id' => $invoice->user_id,
                'error' => $e->getMessage(),
            ]);

            $log->update([
                'status' => 'failed',
                'response' => [
                    'error' => $e->getMessage(),
                ],
            ]);

            throw $e;
        }
    }
}
