<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckInRequest;
use App\Http\Resources\AttendanceResource;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $service) {}

    /**
     * POST /api/attendance/check-in
     *
     * Owner/trainer can check in on behalf of a member by passing user_id.
     * A member calling this endpoint checks themselves in.
     */
    public function checkIn(CheckInRequest $request): JsonResponse
    {
        $authUser = $request->user();
        $gymId    = $authUser->gym_id;

        $target = $this->resolveTargetUser($request, $authUser, $gymId);
        $time   = $request->check_in_time ? Carbon::parse($request->check_in_time) : now();

        try {
            $attendance = $this->service->checkIn($target, $gymId, $time, 'manual');
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return (new AttendanceResource($attendance->load('user')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * POST /api/attendance/check-out
     */
    public function checkOut(Request $request): JsonResponse
    {
        $authUser = $request->user();
        $gymId    = $authUser->gym_id;

        $target = $this->resolveTargetUser($request, $authUser, $gymId);
        $time   = $request->check_out_time ? Carbon::parse($request->check_out_time) : now();

        try {
            $attendance = $this->service->checkOut($target, $gymId, $time, 'manual');
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return new AttendanceResource($attendance->load('user'));
    }

    /**
     * GET /api/attendance/today
     *
     * Returns today's attendance list for the gym (paginated).
     * Members only see their own records.
     */
    public function today(Request $request): JsonResponse
    {
        $authUser = $request->user();
        $gymId    = $authUser->gym_id;

        $filters = $request->only(['source', 'status', 'search', 'per_page']);

        if ($authUser->isMember()) {
            // Members see only their own attendance
            $filters['user_id'] = $authUser->id;
        }

        $records = $this->service->getTodayAttendance($gymId, $filters);
        $summary = $this->service->getTodaySummary($gymId);

        return response()->json([
            'summary' => $summary,
            'data'    => AttendanceResource::collection($records)->resolve(),
            'meta'    => [
                'current_page' => $records->currentPage(),
                'last_page'    => $records->lastPage(),
                'total'        => $records->total(),
            ],
        ]);
    }

    /**
     * GET /api/attendance/my-status
     *
     * Returns the authenticated user's current open check-in (if any).
     */
    public function myStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $open = $this->service->findOpenSession($user->id, $user->gym_id);

        if (! $open) {
            return response()->json(['status' => 'not_checked_in', 'attendance' => null]);
        }

        return response()->json([
            'status'     => 'checked_in',
            'attendance' => new AttendanceResource($open->load('user')),
        ]);
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function resolveTargetUser(Request $request, User $authUser, int $gymId): User
    {
        // Members can only act for themselves
        if ($authUser->isMember()) {
            return $authUser;
        }

        // Owners/trainers/admins can pass a user_id to act on behalf of a member
        if ($request->filled('user_id') && (int) $request->user_id !== $authUser->id) {
            $target = User::forGym($gymId)->findOrFail($request->user_id);

            abort_if(
                $target->gym_id !== $gymId && ! $authUser->isAdmin(),
                403,
                'User does not belong to your gym.'
            );

            return $target;
        }

        return $authUser;
    }
}
