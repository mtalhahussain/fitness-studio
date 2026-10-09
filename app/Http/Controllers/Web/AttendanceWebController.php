<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        $filters = $request->validate([
            'member_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('gym_id', $gymId)],
            'month' => ['nullable', 'date_format:Y-m'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'view' => ['nullable', Rule::in(['list', 'monthly'])],
            'search' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', Rule::in(['manual', 'biometric'])],
            'status' => ['nullable', Rule::in(['checked_in', 'checked_out'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $month = Carbon::createFromFormat('!Y-m', $filters['month'] ?? now()->format('Y-m'));
        $start = isset($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : $month->copy()->startOfMonth();
        $end = isset($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : $month->copy()->endOfMonth();
        if ($end->lt($start) || $start->diffInDays($end) > 366) {
            throw \Illuminate\Validation\ValidationException::withMessages(['end_date' => 'Choose a date range of up to one year, ending on or after the start date.']);
        }
        $filters = array_merge($filters, ['month' => $month->format('Y-m'), 'start_date' => $start->toDateString(), 'end_date' => $end->toDateString(), 'view' => $filters['view'] ?? 'list']);

        $base = Attendance::forGym($gymId)->whereBetween('check_in_time', [$start, $end]);
        if (! empty($filters['member_id'])) {
            $base->where('user_id', $filters['member_id']);
        }
        if (! empty($filters['search'])) {
            $base->whereHas('user', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$filters['search']}%")
                ->orWhere('email', 'like', "%{$filters['search']}%")));
        }
        if (! empty($filters['source'])) {
            $base->where('source', $filters['source']);
        }
        if (! empty($filters['status'])) {
            $filters['status'] === 'checked_in' ? $base->whereNull('check_out_time') : $base->whereNotNull('check_out_time');
        }
        $summary = [
            'total' => (clone $base)->count(),
            'checked_in' => (clone $base)->whereNull('check_out_time')->count(),
            'checked_out' => (clone $base)->whereNotNull('check_out_time')->count(),
        ];
        $records = (clone $base)->with('user:id,name,email')->latest('check_in_time')->paginate($filters['per_page'] ?? 20);
        $records->through(fn ($record) => array_merge($record->toArray(), [
            'status' => $record->isOpen() ? 'checked_in' : 'checked_out',
            'duration_mins' => $record->duration(),
            'is_late_checkout' => $record->isLateCheckout(),
        ]));

        $historyMembers = User::members()->forGym($gymId)->orderBy('name');
        if (! empty($filters['member_id'])) {
            $historyMembers->where('id', $filters['member_id']);
        }
        if (! empty($filters['search'])) {
            $historyMembers->where(fn ($q) => $q->where('name', 'like', "%{$filters['search']}%")
                ->orWhere('email', 'like', "%{$filters['search']}%"));
        }
        $monthly = $historyMembers->paginate($filters['per_page'] ?? 20);
        $punches = (clone $base)->whereIn('user_id', $monthly->pluck('id'))->get()->groupBy('user_id');
        $monthly->through(function ($member) use ($punches) {
            $sessions = $punches->get($member->id, collect());
            $days = $sessions->map(fn ($r) => $r->check_in_time->toDateString())->unique()->values();
            return ['id' => $member->id, 'name' => $member->name, 'email' => $member->email, 'days' => $days, 'present_count' => $days->count(), 'sessions' => $sessions->count()];
        });
        $dates = collect(\Carbon\CarbonPeriod::create($start->copy()->startOfDay(), $end->copy()->startOfDay()))
            ->map(fn ($date) => ['date' => $date->toDateString(), 'label' => $date->format('d M'), 'future' => $date->isFuture() && ! $date->isToday()]);
        $members = User::members()->forGym($gymId)->where('status', 'active')->get(['id', 'name', 'email']);
        $filterMembers = User::members()->forGym($gymId)->orderBy('name')->get(['id', 'name', 'email']);
        $pagination = $filters['view'] === 'monthly' ? $monthly : $records;
        $paginationData = ['current_page' => $pagination->currentPage(), 'last_page' => $pagination->lastPage(), 'total' => $pagination->total()];

        if ($request->wantsJson()) {
            return response()->json(['records' => $records->items(), 'summary' => $summary, 'monthly' => $monthly->items(), 'dates' => $dates, 'pagination' => $paginationData]);
        }

        return view('attendance.index', compact('records', 'summary', 'members', 'filterMembers', 'filters', 'monthly', 'dates', 'paginationData'));
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
