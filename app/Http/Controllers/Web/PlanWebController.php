<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanWebController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $gymId = auth()->user()->gym_id;

        if ($request->wantsJson()) {
            $plans = MembershipPlan::forGym($gymId)
                ->withCount('memberships')
                ->orderBy('type')
                ->orderBy('name')
                ->get();

            return response()->json(['plans' => $plans]);
        }

        $plans = MembershipPlan::forGym($gymId)
            ->withCount('memberships')
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return view('plans.index', compact('plans'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'type'        => ['required', 'in:monthly,quarterly,yearly'],
            'price'       => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'features'    => ['nullable', 'array'],
            'features.*'  => ['string'],
            'is_active'   => ['boolean'],
        ]);

        $data['gym_id']        = auth()->user()->gym_id;
        $data['duration_days'] = MembershipPlan::DURATION_MAP[$data['type']];
        $data['is_active']     = $data['is_active'] ?? true;

        $plan = MembershipPlan::create($data);

        return response()->json(['plan' => $plan->loadCount('memberships')], 201);
    }

    public function update(Request $request, MembershipPlan $plan): JsonResponse
    {
        $this->authorizeGym($plan);

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'type'        => ['required', 'in:monthly,quarterly,yearly'],
            'price'       => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'features'    => ['nullable', 'array'],
            'features.*'  => ['string'],
            'is_active'   => ['boolean'],
        ]);

        $data['duration_days'] = MembershipPlan::DURATION_MAP[$data['type']];

        $plan->update($data);

        return response()->json(['plan' => $plan->loadCount('memberships')]);
    }

    public function destroy(MembershipPlan $plan): JsonResponse
    {
        $this->authorizeGym($plan);

        $plan->delete();

        return response()->json(['message' => 'Plan deleted']);
    }

    private function authorizeGym(MembershipPlan $plan): void
    {
        abort_if($plan->gym_id !== auth()->user()->gym_id, 403);
    }
}
