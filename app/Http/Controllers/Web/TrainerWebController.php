<?php

namespace App\Http\Controllers\Web;

use App\Biometric\DeviceCommandQueue;
use App\Http\Controllers\Controller;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Services\TrainerService;
use Illuminate\Http\Request;

class TrainerWebController extends Controller
{
    public function __construct(private TrainerService $service) {}

    public function index(Request $request)
    {
        $gymId   = auth()->user()->gym_id;
        $filters = $request->validate([
            'search'            => ['nullable', 'string', 'max:100'],
            'trainer_id'        => ['nullable', 'integer'],
            'specialization'    => ['nullable', 'string', 'max:255'],
            'status'            => ['nullable', 'in:active,inactive,suspended'],
            'compensation_mode' => ['nullable', 'in:commission,salary,hourly,mixed'],
            'per_page'          => ['nullable', 'integer', 'min:1', 'max:100'],
            'page'              => ['nullable', 'integer', 'min:1'],
        ]);

        $trainers   = $this->service->getTrainers($gymId, $filters);
        $pagination = ['current_page' => $trainers->currentPage(), 'last_page' => $trainers->lastPage(), 'total' => $trainers->total()];

        if ($request->wantsJson()) {
            return response()->json(['trainers' => $trainers->items(), 'pagination' => $pagination]);
        }

        $members         = User::members()->forGym($gymId)->where('status', 'active')->get(['id', 'name', 'email']);
        $filterTrainers  = User::trainers()->forGym($gymId)->orderBy('name')->get(['id', 'name', 'email']);
        $specializations = TrainerProfile::where('gym_id', $gymId)->whereNotNull('specialization')
            ->distinct()->orderBy('specialization')->pluck('specialization');

        return view('trainers.index', compact('trainers', 'members', 'filterTrainers', 'specializations', 'pagination'));
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
            'hourly_rate'      => ['nullable', 'required_if:compensation_mode,hourly', 'numeric', 'gt:0'],
            'compensation_mode'=> ['required', 'in:commission,salary,hourly,mixed'],
            'base_salary'      => ['nullable', 'required_if:compensation_mode,salary,mixed', 'numeric', 'gt:0'],
            'commission_enabled' => ['nullable', 'boolean'],
            'salary_enabled'   => ['nullable', 'boolean'],
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
            'hourly_rate'      => ['nullable', 'required_if:compensation_mode,hourly', 'numeric', 'gt:0'],
            'compensation_mode'=> ['sometimes', 'in:commission,salary,hourly,mixed'],
            'base_salary'      => ['nullable', 'required_if:compensation_mode,salary,mixed', 'numeric', 'gt:0'],
            'commission_enabled' => ['nullable', 'boolean'],
            'salary_enabled'   => ['nullable', 'boolean'],
        ]);

        $gymId = auth()->user()->gym_id;
        abort_if($gymId && $trainer->gym_id !== $gymId, 403);

        $oldName = $trainer->name;
        $updated = $this->service->updateTrainer($trainer, $data);

        if ($updated->name !== $oldName) {
            app(DeviceCommandQueue::class)->pushUser($updated); // machines show the new name
        }

        return response()->json(['message' => 'Trainer updated successfully.', 'trainer' => $updated]);
    }

    public function destroy(User $trainer)
    {
        $gymId = auth()->user()->gym_id;
        abort_if($gymId && $trainer->gym_id !== $gymId, 403);
        if ($trainer->biometric_code) {
            app(DeviceCommandQueue::class)->removePin($trainer->gym_id, $trainer->biometric_code, $trainer->id);
        }
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
