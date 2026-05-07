<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportWebController extends Controller
{
    public function __construct(private ReportService $service) {}

    public function index(): View
    {
        $year = now()->year;

        return view('reports.index', [
            'revenue'     => $this->service->monthlyRevenue($year),
            'members'     => $this->service->memberGrowth($year),
            'attendance'  => $this->service->attendanceTrends(
                'daily',
                now()->subDays(29)->toDateString(),
                now()->toDateString()
            ),
            'currentYear' => $year,
        ]);
    }

    public function revenueData(Request $request): JsonResponse
    {
        $request->validate(['year' => 'sometimes|integer|min:2000|max:2100']);
        $year = (int) $request->input('year', now()->year);

        return response()->json($this->service->monthlyRevenue($year));
    }

    public function membersData(Request $request): JsonResponse
    {
        $request->validate(['year' => 'sometimes|integer|min:2000|max:2100']);
        $year = (int) $request->input('year', now()->year);

        return response()->json($this->service->memberGrowth($year));
    }

    public function attendanceData(Request $request): JsonResponse
    {
        $request->validate([
            'period'     => 'sometimes|in:daily,weekly,monthly',
            'start_date' => 'sometimes|date',
            'end_date'   => 'sometimes|date|after_or_equal:start_date',
        ]);

        return response()->json($this->service->attendanceTrends(
            $request->input('period', 'daily'),
            $request->input('start_date', now()->subDays(29)->toDateString()),
            $request->input('end_date', now()->toDateString())
        ));
    }
}
