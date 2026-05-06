<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignMembershipRequest;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Http\Resources\MemberResource;
use App\Http\Resources\MembershipResource;
use App\Models\Membership;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function __construct(private MembershipService $service) {}

    public function index(Request $request)
    {
        $this->authorize('members.view');

        $members = $this->service->getMembers(
            gymId: $request->user()->gym_id,
            filters: $request->only(['search', 'status', 'per_page'])
        );

        return MemberResource::collection($members);
    }

    public function store(StoreMemberRequest $request)
    {
        $member = $this->service->createMember(
            data: $request->validated(),
            gymId: $request->user()->gym_id
        );

        return (new MemberResource($member))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, User $member)
    {
        $this->authorize('members.view');
        $this->authorizeGym($request, $member);

        $member->load(['activeMembership.plan', 'memberships.plan']);
        $member->loadCount('memberships');

        return new MemberResource($member);
    }

    public function update(UpdateMemberRequest $request, User $member)
    {
        $this->authorizeGym($request, $member);

        $member->update($request->validated());

        return new MemberResource($member->fresh(['activeMembership.plan']));
    }

    public function destroy(Request $request, User $member)
    {
        $this->authorize('members.delete');
        $this->authorizeGym($request, $member);

        $member->delete();

        return response()->json(['message' => 'Member deleted successfully.']);
    }

    // GET /api/members/{member}/memberships
    public function memberships(Request $request, User $member)
    {
        $this->authorize('members.view');
        $this->authorizeGym($request, $member);

        $memberships = $member->memberships()
            ->with('plan')
            ->latest('start_date')
            ->paginate(10);

        return MembershipResource::collection($memberships);
    }

    // POST /api/members/{member}/memberships
    public function assignMembership(AssignMembershipRequest $request, User $member)
    {
        $this->authorizeGym($request, $member);

        $membership = $this->service->assignMembership(
            member: $member,
            planId: $request->plan_id,
            gymId: $request->user()->gym_id,
            data: $request->validated()
        );

        return (new MembershipResource($membership->load('plan')))
            ->response()
            ->setStatusCode(201);
    }

    // POST /api/memberships/{membership}/renew
    public function renewMembership(Request $request, Membership $membership)
    {
        $this->authorize('members.edit');

        $renewed = $this->service->renewMembership(
            membership: $membership,
            data: $request->only(['amount_paid', 'notes'])
        );

        return new MembershipResource($renewed->load('plan'));
    }

    // POST /api/memberships/{membership}/cancel
    public function cancelMembership(Request $request, Membership $membership)
    {
        $this->authorize('members.edit');

        $cancelled = $this->service->cancelMembership($membership);

        return new MembershipResource($cancelled);
    }

    private function authorizeGym(Request $request, User $member): void
    {
        $gymId = $request->user()->gym_id;

        // Admin can access any gym
        if ($request->user()->isAdmin()) {
            return;
        }

        abort_if($member->gym_id !== $gymId, 403, 'Access denied.');
    }
}
