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
        $gymId   = auth()->user()->gym_id;
        $filters = $request->only(['search', 'source', 'status', 'per_page']);

        $records = $this->service->getTodayAttendance($gymId, $filters);
        $summary = $this->service->getTodaySummary($gymId);
        $members = User::members()->forGym($gymId)->where('status', 'active')->get(['id', 'name', 'email']);

        if ($request->ajax()) {
            return response()->json(['records' => $records->items(), 'summary' => $summary]);
        }

        return view('attendance.index', compact('records', 'summary', 'members'));
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
