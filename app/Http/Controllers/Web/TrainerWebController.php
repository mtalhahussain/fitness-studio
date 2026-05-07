<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TrainerService;
use Illuminate\Http\Request;

class TrainerWebController extends Controller
{
    public function __construct(private TrainerService $service) {}

    public function index(Request $request)
    {
        $gymId   = auth()->user()->gym_id;
        $filters = $request->only(['search', 'specialization', 'per_page']);

        $trainers = $this->service->getTrainers($gymId, $filters);
        $members  = User::members()->forGym($gymId)->where('status', 'active')->get(['id', 'name', 'email']);

        if ($request->wantsJson()) {
            return response()->json(['trainers' => $trainers->items()]);
        }

        return view('trainers.index', compact('trainers', 'members'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'email'            => ['required', 'email', 'unique:users,email'],
            'phone'            => ['nullable', 'string', 'max:20'],
            'password'         => ['required', 'string', 'min:6'],
            'specialization'   => ['required', 'string', 'max:255'],
            'experience_years' => ['nullable', 'integer', 'min:0'],
            'hourly_rate'      => ['nullable', 'numeric', 'min:0'],
        ]);

        $trainer = $this->service->createTrainer($data, auth()->user()->gym_id);

        return response()->json(['message' => 'Trainer created successfully.', 'trainer' => $trainer], 201);
    }

    public function update(Request $request, User $trainer)
    {
        $data = $request->validate([
            'name'             => ['sometimes', 'string', 'max:255'],
            'phone'            => ['nullable', 'string', 'max:20'],
            'status'           => ['sometimes', 'in:active,inactive,suspended'],
            'specialization'   => ['sometimes', 'string', 'max:255'],
            'experience_years' => ['nullable', 'integer', 'min:0'],
            'hourly_rate'      => ['nullable', 'numeric', 'min:0'],
        ]);

        $updated = $this->service->updateTrainer($trainer, $data);

        return response()->json(['message' => 'Trainer updated successfully.', 'trainer' => $updated]);
    }

    public function destroy(User $trainer)
    {
        $gymId = auth()->user()->gym_id;
        abort_if($gymId && $trainer->gym_id !== $gymId, 403);
        $trainer->delete();

        return response()->json(['message' => 'Trainer deleted successfully.']);
    }

    public function assignMember(Request $request, User $trainer)
    {
        $request->validate(['member_id' => ['required', 'integer', 'exists:users,id']]);

        $gymId  = auth()->user()->gym_id;
        $member = User::forGym($gymId)->findOrFail($request->member_id);

        try {
            $this->service->assignMember($trainer, $member, $gymId);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json(['message' => "Member assigned to {$trainer->name} successfully."]);
    }

    public function schedule(Request $request, User $trainer)
    {
        $gymId    = auth()->user()->gym_id;
        $sessions = $this->service->getSchedule($trainer, $gymId, $request->only(['from', 'to', 'status']));

        return response()->json(['sessions' => $sessions->load(['member:id,name,email'])]);
    }

    public function createSession(Request $request, User $trainer)
    {
        $data = $request->validate([
            'title'         => ['required', 'string', 'max:255'],
            'member_id'     => ['nullable', 'integer', 'exists:users,id'],
            'scheduled_at'  => ['required', 'date'],
            'duration_mins' => ['nullable', 'integer', 'min:15', 'max:480'],
            'session_type'  => ['nullable', 'in:personal,group'],
            'notes'         => ['nullable', 'string'],
        ]);

        try {
            $session = $this->service->createSession($trainer, auth()->user()->gym_id, $data);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json(['message' => 'Session scheduled successfully.', 'session' => $session->load(['trainer', 'member'])], 201);
    }
}
