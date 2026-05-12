<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Http\Request;

class AttendanceWebController extends Controller
{
    public function __construct(private AttendanceService $service) {}

    public function index(Request $request)
    {
        $user = auth()->user();

        if ($user->isMember() || $user->isTrainer()) {
            return $this->myHistory($request, $user);
        }

        $gymId   = $user->isAdmin() ? (int) session('admin_active_gym_id') : $user->gym_id;
        $filters = $request->only(['search', 'source', 'status', 'per_page']);

        $records = $this->service->getTodayAttendance($gymId, $filters);
        $summary = $this->service->getTodaySummary($gymId);
        $members = User::members()->forGym($gymId)->where('status', 'active')->get(['id', 'name', 'email']);

        if ($request->wantsJson()) {
            return response()->json(['records' => $records->items(), 'summary' => $summary]);
        }

        return view('attendance.index', compact('records', 'summary', 'members'));
    }

    private function myHistory(Request $request, $user)
    {
        $month = $request->get('month', now()->format('Y-m'));

        // Validate and parse the selected month
        try {
            $monthStart = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Exception $e) {
            $monthStart = now()->startOfMonth();
            $month = $monthStart->format('Y-m');
        }
        $monthEnd = $monthStart->copy()->endOfMonth();

        // All records for selected month
        $records = \App\Models\Attendance::where('user_id', $user->id)
            ->whereBetween('check_in_time', [$monthStart, $monthEnd])
            ->latest('check_in_time')
            ->get();

        // Unique present days (as 'd' keys for quick lookup)
        $presentDays = $records->map(fn ($r) => $r->check_in_time->format('Y-m-d'))->unique()->values();

        $daysInMonth  = $monthStart->daysInMonth;
        $presentCount = $presentDays->count();
        // Absent = days up to today (can't be absent on future days)
        $daysSoFar    = $monthStart->isSameMonth(now()) ? now()->day : $daysInMonth;
        $absentCount  = $daysSoFar - $presentCount;

        $todayRecord = \App\Models\Attendance::where('user_id', $user->id)
            ->whereDate('check_in_time', today())
            ->latest('check_in_time')
            ->first();

        $totalCheckins = \App\Models\Attendance::where('user_id', $user->id)->count();

        // Build month options: current month going back 12 months
        $monthOptions = collect(range(0, 11))->map(fn ($i) => [
            'value' => now()->subMonths($i)->format('Y-m'),
            'label' => now()->subMonths($i)->format('M Y'),
        ]);

        return view('attendance.my-history', compact(
            'records', 'presentDays', 'presentCount', 'absentCount',
            'daysInMonth', 'daysSoFar', 'monthStart', 'month',
            'monthOptions', 'todayRecord', 'totalCheckins'
        ));
    }

    public function checkIn(Request $request)
    {
        $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);

        $gymId  = auth()->user()->gym_id;
        $target = User::forGym($gymId)->findOrFail($request->user_id);

        try {
            $record = $this->service->checkIn($target, $gymId, now(), 'manual');
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'message'    => "{$target->name} checked in successfully.",
            'attendance' => $record->load('user'),
        ], 201);
    }

    public function checkOut(Request $request)
    {
        $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);

        $gymId  = auth()->user()->gym_id;
        $target = User::forGym($gymId)->findOrFail($request->user_id);

        try {
            $record = $this->service->checkOut($target, $gymId, now(), 'manual');
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'message'    => "{$target->name} checked out successfully.",
            'attendance' => $record->load('user'),
        ]);
    }
}
