<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Services\PaymentDueReminderService;
use Illuminate\Http\Request;

class WhatsAppReminderWebController extends Controller
{
    public function __construct(private PaymentDueReminderService $service) {}

    public function index(Request $request)
    {
        $gym = $this->resolveGym();

        $filters = $request->only(['month', 'status', 'search']);
        $invoices = $this->service->getDueInvoicesForOwner((int) $gym->id, $filters);
        $messageTemplate = $this->service->getMessageTemplate($gym);

        return view('whatsapp-reminders.index', [
            'gym' => $gym,
            'invoices' => $invoices,
            'filters' => [
                'month' => $filters['month'] ?? now()->format('Y-m'),
                'status' => $filters['status'] ?? 'all',
                'search' => $filters['search'] ?? '',
            ],
            'messageTemplate' => $messageTemplate,
        ]);
    }

    public function send(Request $request)
    {
        $gym = $this->resolveGym();

        $data = $request->validate([
            'invoice_ids' => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['integer', 'distinct'],
        ]);

        $result = $this->service->queueManualRemindersForInvoices($gym, $data['invoice_ids']);

        return response()->json([
            'message' => 'WhatsApp reminders queued successfully.',
            'result' => $result,
        ]);
    }

    public function updateTemplate(Request $request)
    {
        $gym = $this->resolveGym();

        $data = $request->validate([
            'whatsapp_message_template' => ['required', 'string', 'max:2000'],
        ]);

        $gym->update([
            'whatsapp_message_template' => $data['whatsapp_message_template'],
        ]);

        return response()->json([
            'message' => 'Reminder message template updated.',
            'template' => $gym->fresh()->whatsapp_message_template,
        ]);
    }

    private function resolveGym(): Gym
    {
        $user = auth()->user();
        $gymId = $user->isAdmin() ? (int) session('admin_active_gym_id') : (int) $user->gym_id;

        abort_if(! $gymId, 403, 'No gym context found.');

        $gym = Gym::find($gymId);
        abort_if(! $gym, 404, 'Gym not found.');
        abort_if(! $gym->hasModule('whatsapp'), 403, 'WhatsApp module is not enabled for this gym.');

        return $gym;
    }
}
