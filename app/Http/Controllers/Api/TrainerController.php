<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignMemberToTrainerRequest;
use App\Http\Requests\StoreTrainerRequest;
use App\Http\Requests\StoreTrainingSessionRequest;
use App\Http\Resources\MemberResource;
use App\Http\Resources\TrainerResource;
use App\Http\Resources\TrainingSessionResource;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\TrainerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainerController extends Controller
{
    public function __construct(private TrainerService $service) {}

    // ── Trainer CRUD ──────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $this->authorize('trainers.view');

        $trainers = $this->service->getTrainers(
            gymId:   $request->user()->gym_id,
            filters: $request->only(['search', 'specialization', 'per_page'])
        );

        return response()->json(TrainerResource::collection($trainers)->response()->getData(true));
    }

    public function store(StoreTrainerRequest $request): JsonResponse
    {
        $trainer = $this->service->createTrainer(
            data:  $request->validated(),
            gymId: $request->user()->gym_id
        );

        return (new TrainerResource($trainer))->response()->setStatusCode(201);
    }

    public function show(Request $request, User $trainer): JsonResponse
    {
        $this->authorize('trainers.view');
        $this->assertSameGym($request, $trainer);

        $trainer->load(['trainerProfile', 'assignedMembers'])
                ->loadCount(['trainingSessions', 'assignedMembers']);

        return (new TrainerResource($trainer))->response();
    }

    public function update(Request $request, User $trainer): JsonResponse
    {
        $this->authorize('trainers.edit');
        $this->assertSameGym($request, $trainer);

        $request->validate([
            'name'             => ['sometimes', 'string', 'max:255'],
            'phone'            => ['nullable', 'string', 'max:20'],
            'status'           => ['sometimes', 'in:active,inactive,suspended'],
            'specialization'   => ['sometimes', 'string', 'max:255'],
            'bio'              => ['nullable', 'string'],
            'experience_years' => ['nullable', 'integer', 'min:0'],
            'certifications'   => ['nullable', 'array'],
            'certifications.*' => ['string'],
            'hourly_rate'      => ['nullable', 'numeric', 'min:0'],
            'is_active'        => ['sometimes', 'boolean'],
        ]);

        $updated = $this->service->updateTrainer($trainer, $request->all());

        return (new TrainerResource($updated))->response();
    }

    public function destroy(Request $request, User $trainer): JsonResponse
    {
        $this->authorize('trainers.delete');
        $this->assertSameGym($request, $trainer);

        $trainer->delete();

        return response()->json(['message' => 'Trainer deleted successfully.']);
    }

    // ── Member Assignment ─────────────────────────────────────────────────────

    public function assignMember(AssignMemberToTrainerRequest $request, User $trainer): JsonResponse
    {
        $this->assertSameGym($request, $trainer);

        $member = User::forGym($request->user()->gym_id)->findOrFail($request->member_id);

        try {
            $this->service->assignMember($trainer, $member, $request->user()->gym_id, $request->only('notes'));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json(['message' => 'Member assigned to trainer successfully.']);
    }

    public function unassignMember(Request $request, User $trainer, User $member): JsonResponse
    {
        $this->authorize('trainers.edit');
        $this->assertSameGym($request, $trainer);

        try {
            $this->service->unassignMember($trainer, $member, $request->user()->gym_id);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json(['message' => 'Member unassigned successfully.']);
    }

    public function assignedMembers(Request $request, User $trainer): JsonResponse
    {
        $this->authorize('trainers.view');
        $this->assertSameGym($request, $trainer);

        $members = $this->service->getAssignedMembers(
            trainer: $trainer,
            gymId:   $request->user()->gym_id,
            filters: $request->only(['search', 'per_page'])
        );

        return response()->json(MemberResource::collection($members)->response()->getData(true));
    }

    // ── Training Sessions ─────────────────────────────────────────────────────

    public function createSession(StoreTrainingSessionRequest $request, User $trainer): JsonResponse
    {
        $this->assertSameGym($request, $trainer);

        try {
            $session = $this->service->createSession(
                trainer: $trainer,
                gymId:   $request->user()->gym_id,
                data:    $request->validated()
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return (new TrainingSessionResource($session->load(['trainer', 'member'])))
            ->response()
            ->setStatusCode(201);
    }

    public function schedule(Request $request, User $trainer): JsonResponse
    {
        $this->authorize('schedule.view');
        $this->assertSameGym($request, $trainer);

        $sessions = $this->service->getSchedule(
            trainer: $trainer,
            gymId:   $request->user()->gym_id,
            filters: $request->only(['from', 'to', 'status'])
        );

        return response()->json([
            'data'  => TrainingSessionResource::collection($sessions),
            'total' => $sessions->count(),
        ]);
    }

    public function updateSession(Request $request, TrainingSession $session): JsonResponse
    {
        $this->authorize('schedule.manage');

        $request->validate([
            'title'         => ['sometimes', 'string', 'max:255'],
            'scheduled_at'  => ['sometimes', 'date'],
            'duration_mins' => ['sometimes', 'integer', 'min:15', 'max:480'],
            'status'        => ['sometimes', 'in:scheduled,completed,cancelled,no_show'],
            'notes'         => ['nullable', 'string'],
        ]);

        $updated = $this->service->updateSession($session, $request->all());

        return (new TrainingSessionResource($updated))->response();
    }

    public function upcomingSessions(Request $request): JsonResponse
    {
        $this->authorize('schedule.view');

        $sessions = $this->service->getUpcomingSessions(
            gymId:   $request->user()->gym_id,
            filters: $request->only(['trainer_id', 'per_page'])
        );

        return response()->json(TrainingSessionResource::collection($sessions)->response()->getData(true));
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function assertSameGym(Request $request, User $trainer): void
    {
        if ($request->user()->isAdmin()) {
            return;
        }

        abort_if($trainer->gym_id !== $request->user()->gym_id, 403, 'Access denied.');
    }
}
