<?php

namespace App\Listeners;

use App\Events\PaymentReceived;
use App\Services\TrainerCommissionService;
use Illuminate\Support\Facades\Log;

class CalculateTrainerCommission
{
    public function __construct(private TrainerCommissionService $commissionService) {}

    public function handle(PaymentReceived $event): void
    {
        try {
            $invoice = $event->invoice->loadMissing('trainer');

            if (!$invoice->trainer_id) {
                return;
            }

            $this->commissionService->calculateFromPayment($event->payment, $invoice);
        } catch (\Throwable $e) {
            Log::error('Commission calculation failed', [
                'payment_id' => $event->payment->id,
                'invoice_id' => $event->invoice->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
