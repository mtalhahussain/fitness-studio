<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Http\Request;

class MemberWebController extends Controller
{
    public function __construct(private MembershipService $service) {}

    public function index(Request $request)
    {
        $gymId   = auth()->user()->gym_id;
        $filters = $request->only(['search', 'status', 'per_page']);

        $members = $this->service->getMembers($gymId, $filters);
        $plans   = MembershipPlan::forGym($gymId)->active()->get();

        if ($request->ajax()) {
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
            'plan_id'  => ['nullable', 'integer', 'exists:membership_plans,id'],
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
        abort_if($member->gym_id !== auth()->user()->gym_id, 403);
        $member->delete();

        return response()->json(['message' => 'Member deleted successfully.']);
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
