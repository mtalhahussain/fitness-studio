<?php

namespace App\Services;

use App\Jobs\SendPaymentDueReminderJob;
use App\Models\Gym;
use App\Models\Invoice;
use App\Models\WhatsAppMessageLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PaymentDueReminderService
{
    public const DEFAULT_MESSAGE_TEMPLATE = 'Hi {name}, your payment for invoice {invoice_number} is due. Amount due: PKR {amount_due}. Due date: {due_date}.';

    public function queueDueReminders(?int $gymId = null): array
    {
        $templateName = config('whatsapp.reminders.template_name', 'payment_due');
        $today = today()->toDateString();

        $gyms = Gym::query()
            ->when($gymId, fn (Builder $q) => $q->where('id', $gymId))
            ->where('whatsapp_enabled', true)
            ->get()
            ->filter(fn (Gym $gym) => $gym->hasModule('whatsapp'));

        if ($gyms->isEmpty()) {
            Log::info('No gyms with WhatsApp reminders enabled/module access found.');
            return [
                'total_due' => 0,
                'queued' => 0,
                'skipped' => 0,
            ];
        }

        $totalDue = 0;
        $queued = 0;
        $skipped = 0;

        foreach ($gyms as $gym) {
            $gymTemplateName = $gym->whatsapp_template_name ?: $templateName;
            $gymLanguage = $gym->whatsapp_template_language ?: 'en_US';

            $invoices = $this->baseDueInvoicesQuery($gym->id)
                ->whereDate('due_date', '<=', today())
                ->get();

            $totalDue += $invoices->count();

            foreach ($invoices as $invoice) {
                if ($this->alreadyRemindedToday((int) $invoice->id, $gymTemplateName, $today)) {
                    $skipped++;
                    continue;
                }

                $log = WhatsAppMessageLog::firstOrCreate([
                    'invoice_id' => $invoice->id,
                    'template_name' => $gymTemplateName,
                    'reminder_date' => $today,
                ], [
                    'user_id' => $invoice->user_id,
                    'phone' => (string) $invoice->user?->phone,
                    'message_body' => $this->renderMessageForInvoice($gym, $invoice),
                    'status' => 'queued',
                ]);

                if (! $log->wasRecentlyCreated) {
                    $skipped++;
                    continue;
                }

                SendPaymentDueReminderJob::dispatch(
                    logId: (int) $log->id,
                    phone: (string) $invoice->user?->phone,
                    templateName: $gymTemplateName,
                    components: $this->buildTemplateComponents($invoice),
                    gymId: $gym->id,
                    language: $gymLanguage,
                );

                $queued++;
            }
        }

        return [
            'total_due' => $totalDue,
            'queued' => $queued,
            'skipped' => $skipped,
        ];
    }

    public function getDueInvoicesForOwner(int $gymId, array $filters = []): Collection
    {
        $month = (string) ($filters['month'] ?? now()->format('Y-m'));
        $status = (string) ($filters['status'] ?? 'all');
        $search = trim((string) ($filters['search'] ?? ''));

        $query = $this->baseDueInvoicesQuery($gymId)
            ->whereYear('due_date', (int) substr($month, 0, 4))
            ->whereMonth('due_date', (int) substr($month, 5, 2));

        if ($status === 'overdue') {
            $query->whereDate('due_date', '<', today());
        } elseif ($status === 'today') {
            $query->whereDate('due_date', today());
        }

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('user', fn (Builder $u) => $u
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%"));
            });
        }

        return $query->orderBy('due_date')->get();
    }

    public function queueManualRemindersForInvoices(Gym $gym, array $invoiceIds): array
    {
        $templateName = $gym->whatsapp_template_name ?: config('whatsapp.reminders.template_name', 'payment_due');
        $language = $gym->whatsapp_template_language ?: 'en_US';
        $today = today()->toDateString();

        $invoices = $this->baseDueInvoicesQuery($gym->id)
            ->whereIn('id', $invoiceIds)
            ->get();

        $queued = 0;
        $skipped = 0;

        foreach ($invoices as $invoice) {
            if ($this->alreadyRemindedToday((int) $invoice->id, $templateName, $today)) {
                $skipped++;
                continue;
            }

            $log = WhatsAppMessageLog::firstOrCreate([
                'invoice_id' => $invoice->id,
                'template_name' => $templateName,
                'reminder_date' => $today,
            ], [
                'user_id' => $invoice->user_id,
                'phone' => (string) $invoice->user?->phone,
                'message_body' => $this->renderMessageForInvoice($gym, $invoice),
                'status' => 'queued',
            ]);

            if (! $log->wasRecentlyCreated) {
                $skipped++;
                continue;
            }

            SendPaymentDueReminderJob::dispatch(
                logId: (int) $log->id,
                phone: (string) $invoice->user?->phone,
                templateName: $templateName,
                components: $this->buildTemplateComponents($invoice),
                gymId: $gym->id,
                language: $language,
            );

            $queued++;
        }

        return [
            'total' => $invoices->count(),
            'queued' => $queued,
            'skipped' => $skipped,
        ];
    }

    public function getMessageTemplate(Gym $gym): string
    {
        return $gym->whatsapp_message_template ?: self::DEFAULT_MESSAGE_TEMPLATE;
    }

    public function renderMessageForInvoice(Gym $gym, Invoice $invoice): string
    {
        $template = $this->getMessageTemplate($gym);

        $replacements = [
            '{name}' => (string) ($invoice->user?->name ?? 'Member'),
            '{invoice_number}' => (string) $invoice->invoice_number,
            '{amount_due}' => number_format($invoice->amountDue(), 2),
            '{due_date}' => optional($invoice->due_date)->format('d-M-Y') ?? now()->toDateString(),
        ];

        return strtr($template, $replacements);
    }

    public function buildTemplateComponents(Invoice $invoice): array
    {
        return [
            [
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => (string) ($invoice->user?->name ?? 'Member')],
                    ['type' => 'text', 'text' => (string) $invoice->invoice_number],
                    ['type' => 'text', 'text' => number_format($invoice->amountDue(), 2)],
                    ['type' => 'text', 'text' => optional($invoice->due_date)->format('d-M-Y') ?? now()->toDateString()],
                ],
            ],
        ];
    }

    private function baseDueInvoicesQuery(int $gymId): Builder
    {
        return Invoice::query()
            ->forGym($gymId)
            ->with(['user:id,name,phone'])
            ->withSum('payments', 'amount')
            ->whereNotNull('due_date')
            ->whereIn('status', ['unpaid', 'partially_paid'])
            ->whereHas('user', fn (Builder $q) => $q->whereNotNull('phone')->where('phone', '!=', ''));
    }

    private function alreadyRemindedToday(int $invoiceId, string $templateName, string $today): bool
    {
        return WhatsAppMessageLog::query()
            ->where('invoice_id', $invoiceId)
            ->where('template_name', $templateName)
            ->where('reminder_date', $today)
            ->exists();
    }
}
