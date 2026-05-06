<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMembershipPlanRequest;
use App\Http\Resources\MembershipPlanResource;
use App\Models\MembershipPlan;
use Illuminate\Http\Request;

class MembershipPlanController extends Controller
{
    public function index(Request $request)
    {
        $plans = MembershipPlan::forGym($request->user()->gym_id)
            ->active()
            ->get();

        return MembershipPlanResource::collection($plans);
    }

    public function store(StoreMembershipPlanRequest $request)
    {
        $data = $request->validated();

        // Auto-set duration_days from plan type
        $data['duration_days'] = MembershipPlan::DURATION_MAP[$data['type']];
        $data['gym_id']        = $request->user()->gym_id;

        $plan = MembershipPlan::create($data);

        return (new MembershipPlanResource($plan))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, MembershipPlan $membershipPlan)
    {
        abort_if(
            $membershipPlan->gym_id !== $request->user()->gym_id && ! $request->user()->isAdmin(),
            403
        );

        return new MembershipPlanResource($membershipPlan);
    }

    public function update(StoreMembershipPlanRequest $request, MembershipPlan $membershipPlan)
    {
        $data = $request->validated();

        if (isset($data['type'])) {
            $data['duration_days'] = MembershipPlan::DURATION_MAP[$data['type']];
        }

        $membershipPlan->update($data);

        return new MembershipPlanResource($membershipPlan->fresh());
    }

    public function destroy(Request $request, MembershipPlan $membershipPlan)
    {
        $this->authorize('gym.settings');

        $membershipPlan->delete();

        return response()->json(['message' => 'Plan deleted.']);
    }
}
