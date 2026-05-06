<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $service) {}

    /**
     * GET /api/dashboard
     *
     * Returns core KPIs for the authenticated user's gym.
     * Admin (gym_id = null) gets aggregate across all gyms.
     */
    public function index(Request $request): JsonResponse
    {
        $gymId = auth()->user()->gym_id;

        $stats = $this->service->getStats($gymId);

        return response()->json([
            'stats'        => $stats,
            'gym_id'       => $gymId,
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
