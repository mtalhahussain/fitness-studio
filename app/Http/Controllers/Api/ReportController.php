<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private ReportService $service) {}

    /**
     * GET /api/reports/revenue
     *
     * Query params:
     *   year  int  (default: current year)
     */
    public function revenue(Request $request): JsonResponse
    {
        $request->validate(['year' => 'sometimes|integer|min:2000|max:2100']);

        $year = (int) $request->input('year', now()->year);

        $data = $this->service->monthlyRevenue($year);

        return response()->json([
            'report'       => 'monthly_revenue',
            'data'         => $data,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/reports/members
     *
     * Query params:
     *   year  int  (default: current year)
     */
    public function memberGrowth(Request $request): JsonResponse
    {
        $request->validate(['year' => 'sometimes|integer|min:2000|max:2100']);

        $year = (int) $request->input('year', now()->year);

        $data = $this->service->memberGrowth($year);

        return response()->json([
            'report'       => 'member_growth',
            'data'         => $data,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/reports/attendance
     *
     * Query params:
     *   period      string  daily|weekly|monthly  (default: daily)
     *   start_date  date    (default: 30 days ago)
     *   end_date    date    (default: today)
     */
    public function attendanceTrends(Request $request): JsonResponse
    {
        $request->validate([
            'period'     => 'sometimes|in:daily,weekly,monthly',
            'start_date' => 'sometimes|date',
            'end_date'   => 'sometimes|date|after_or_equal:start_date',
        ]);

        $period    = $request->input('period', 'daily');
        $startDate = $request->input('start_date', now()->subDays(29)->toDateString());
        $endDate   = $request->input('end_date', now()->toDateString());

        $data = $this->service->attendanceTrends($period, $startDate, $endDate);

        return response()->json([
            'report'       => 'attendance_trends',
            'data'         => $data,
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
