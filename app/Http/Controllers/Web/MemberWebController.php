<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\POSService;
use Illuminate\Http\Request;

class MemberWebController extends Controller
{
    public function __construct(
        private MembershipService $service,
        private POSService $posService,
    ) {}

    public function index(Request $request)
    {
        $gymId   = auth()->user()->gym_id;
        $filters = $request->only(['search', 'status', 'per_page']);

        $members = $this->service->getMembers($gymId, $filters);
        $plans   = MembershipPlan::forGym($gymId)->active()->get();

        if ($request->wantsJson()) {
            return response()->json([
                'members' => $members->items(),
                'meta'    => [
                    'total'        => $members->total(),
                    'current_page' => $members->currentPage(),
                    'last_page'    => $members->lastPage(),
                ],
            ]);
        }

        return view('members.index', compact('members', 'plans'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'phone'    => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:6'],
            'plan_id'     => ['nullable', 'integer', 'exists:membership_plans,id'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
        ]);

        $member = $this->service->createMember($data, auth()->user()->gym_id);

        return response()->json(['message' => 'Member created successfully.', 'member' => $member], 201);
    }

    public function update(Request $request, User $member)
    {
        $data = $request->validate([
            'name'   => ['sometimes', 'string', 'max:255'],
            'phone'  => ['nullable', 'string', 'max:20'],
            'status' => ['sometimes', 'in:active,inactive,suspended'],
        ]);

        $member->update($data);

        return response()->json(['message' => 'Member updated successfully.', 'member' => $member->fresh()]);
    }

    public function destroy(User $member)
    {
        $gymId = auth()->user()->gym_id;
        abort_if($gymId && $member->gym_id !== $gymId, 403);
        $member->delete();

        return response()->json(['message' => 'Member deleted successfully.']);
    }

    public function payBalance(Request $request, User $member)
    {
        $gymId = auth()->user()->gym_id;
        abort_if($gymId && $member->gym_id !== $gymId, 403);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:cash,card,bank_transfer,wallet,other'],
        ]);

        $membership = $member->activeMembership()->with('invoice')->first();
        abort_if(! $membership, 404, 'No active membership found.');

        $planPrice  = (float) ($membership->plan?->price ?? 0);
        $amountPaid = (float) $membership->amount_paid;
        $balance    = $planPrice - $amountPaid;

        if ($balance <= 0) {
            return response()->json(['message' => 'No balance due for this membership.'], 422);
        }

        $newPaid = min($amountPaid + (float) $data['amount'], $planPrice);
        $membership->update(['amount_paid' => $newPaid]);

        // also record on invoice if one exists and is not fully settled
        $invoice = $membership->invoice;
        if ($invoice && ! $invoice->isCancelled() && ! $invoice->isPaid()) {
            $this->posService->addPayment($invoice, $data);
        }

        return response()->json([
            'message'    => 'Payment recorded successfully.',
            'amount_due' => max(0, $planPrice - $newPaid),
        ]);
    }

    public function assignMembership(Request $request, User $member)
    {
        $data = $request->validate([
            'plan_id'     => ['required', 'integer', 'exists:membership_plans,id'],
            'start_date'  => ['nullable', 'date'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
        ]);

        $membership = $this->service->assignMembership($member, $data['plan_id'], auth()->user()->gym_id, $data);

        return response()->json(['message' => 'Membership assigned successfully.', 'membership' => $membership->load('plan')]);
    }
}
