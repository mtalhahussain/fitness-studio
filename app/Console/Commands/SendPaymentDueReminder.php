<?php

namespace App\Console\Commands;

use App\Services\PaymentDueReminderService;
use Illuminate\Console\Command;
use Throwable;

class SendPaymentDueReminder extends Command
{
    protected $signature = 'reminders:payment-due';

    protected $description = 'Queue WhatsApp reminders for due/overdue unpaid payments.';

    public function __construct(private PaymentDueReminderService $paymentDueReminderService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = $this->paymentDueReminderService->queueDueReminders();

            $this->info('Payment due reminder job dispatch complete.');
            $this->line("Total due: {$result['total_due']}");
            $this->line("Queued: {$result['queued']}");
            $this->line("Skipped (already reminded): {$result['skipped']}");

            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            $this->error('Failed to queue payment due reminders: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
