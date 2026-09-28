<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\MemberTrainingPeriod;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\TrainerService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Trainer's own workspace: assigned members, their attendance, and sessions.
 * Every action is limited to members the logged-in trainer is responsible for.
 */
class TrainerPortalController extends Controller
{
    /** Quick-pick training titles shown in the "Add Session" form. */
    public const TRAINING_TYPES = [
        'Strength Training', 'Cardio', 'Weight Loss', 'Muscle Gain',
        'HIIT', 'Yoga / Stretching', 'Diet Consultation', 'Fitness Assessment',
    ];

    public function __construct(private TrainerService $service) {}

    // ── Members ───────────────────────────────────────────────────────────────

    public function members(Request $request)
    {
        $trainer = auth()->user();
        $ids     = $this->service->myMemberIds($trainer, $trainer->gym_id);
        $search  = trim((string) $request->get('search', ''));
        $filter  = $request->get('filter', 'all');

        $presentToday = Attendance::whereIn('user_id', $ids)
            ->whereDate('check_in_time', today())
            ->pluck('user_id')->unique()->flip();

        $members = User::members()
            ->whereIn('id', $ids)
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
            ))
            ->when($filter === 'present', fn ($q) => $q->whereIn('id', $presentToday->keys()))
            ->when($filter === 'absent', fn ($q) => $q->whereNotIn('id', $presentToday->keys()))
            ->with('activeMembership.plan')
            ->orderBy('name')
            ->get();

        $monthDays = Attendance::whereIn('user_id', $ids)
            ->where('check_in_time', '>=', now()->startOfMonth())
            ->selectRaw('user_id, COUNT(DISTINCT DATE(check_in_time)) as days')
            ->groupBy('user_id')
            ->pluck('days', 'user_id');

        $lastVisit = Attendance::whereIn('user_id', $ids)
            ->selectRaw('user_id, MAX(check_in_time) as last_at')
            ->groupBy('user_id')
            ->pluck('last_at', 'user_id');

        $nextSession = TrainingSession::forTrainer($trainer->id)
            ->whereIn('member_id', $ids)
            ->upcoming()
            ->orderBy('scheduled_at')
            ->get()
            ->groupBy('member_id')
            ->map->first();

        $stats = [
            'total'          => $ids->count(),
            'present_today'  => $presentToday->count(),
            'sessions_today' => TrainingSession::forTrainer($trainer->id)->whereDate('scheduled_at', today())->where('status', '!=', 'cancelled')->count(),
            'upcoming'       => TrainingSession::forTrainer($trainer->id)->upcoming()->count(),
        ];

        $formMembers   = $this->formMembers($ids);
        $trainingTypes = self::TRAINING_TYPES;

        return view('trainer.members', compact(
            'members', 'presentToday', 'monthDays', 'lastVisit', 'nextSession',
            'stats', 'search', 'filter', 'formMembers', 'trainingTypes'
        ));
    }

    public function showMember(Request $request, User $member)
    {
        $trainer = auth()->user();
        $ids     = $this->service->myMemberIds($trainer, $trainer->gym_id);
        abort_unless($ids->contains($member->id), 403, 'This member is not assigned to you.');

        try {
            $monthStart = Carbon::createFromFormat('Y-m', (string) $request->get('month', now()->format('Y-m')))->startOfMonth();
        } catch (\Exception) {
            $monthStart = now()->startOfMonth();
        }
        $monthEnd = $monthStart->copy()->endOfMonth();

        $records = Attendance::where('user_id', $member->id)
            ->whereBetween('check_in_time', [$monthStart, $monthEnd])
            ->latest('check_in_time')
            ->get();

        $presentDays  = $records->map(fn ($r) => $r->check_in_time->format('Y-m-d'))->unique()->flip();
        $daysSoFar    = $monthStart->isSameMonth(now()) ? now()->day : ($monthStart->isFuture() ? 0 : $monthStart->daysInMonth);
        $presentCount = $presentDays->count();

        $upcoming = TrainingSession::forTrainer($trainer->id)->forMember($member->id)
            ->upcoming()->orderBy('scheduled_at')->get();

        $history = TrainingSession::forTrainer($trainer->id)->forMember($member->id)
            ->where(fn ($q) => $q->where('scheduled_at', '<', now())->orWhere('status', '!=', 'scheduled'))
            ->latest('scheduled_at')->limit(20)->get();

        $period = MemberTrainingPeriod::forTrainer($trainer->id)->forMember($member->id)
            ->whereIn('status', ['active', 'paused'])->latest('start_date')->first();

        $member->load('activeMembership.plan');

        $monthOptions = collect(range(0, 11))->map(fn ($i) => [
            'value' => now()->subMonths($i)->format('Y-m'),
            'label' => now()->subMonths($i)->format('M Y'),
        ]);

        $formMembers   = $this->formMembers($ids);
        $trainingTypes = self::TRAINING_TYPES;

        return view('trainer.member-show', compact(
            'member', 'records', 'presentDays', 'presentCount', 'daysSoFar', 'monthStart',
            'upcoming', 'history', 'period', 'monthOptions', 'formMembers', 'trainingTypes'
        ));
    }

    // ── Sessions ──────────────────────────────────────────────────────────────

    public function sessions(Request $request)
    {
        $trainer = auth()->user();
        $tab     = in_array($request->get('tab'), ['today', 'upcoming', 'past']) ? $request->get('tab') : 'upcoming';

        $query = TrainingSession::forTrainer($trainer->id)->with('member:id,name,phone');

        match ($tab) {
            'today'    => $query->whereDate('scheduled_at', today())->orderBy('scheduled_at'),
            'upcoming' => $query->upcoming()->orderBy('scheduled_at'),
            'past'     => $query->where(fn ($q) => $q->where('scheduled_at', '<', now())->orWhere('status', '!=', 'scheduled'))
                                ->latest('scheduled_at'),
        };

        $sessions = $query->paginate(20)->withQueryString();

        $counts = [
            'today'    => TrainingSession::forTrainer($trainer->id)->whereDate('scheduled_at', today())->count(),
            'upcoming' => TrainingSession::forTrainer($trainer->id)->upcoming()->count(),
            'pending'  => TrainingSession::forTrainer($trainer->id)->where('status', 'scheduled')->where('scheduled_at', '<', now())->count(),
        ];

        $formMembers   = $this->formMembers($this->service->myMemberIds($trainer, $trainer->gym_id));
        $trainingTypes = self::TRAINING_TYPES;

        return view('trainer.sessions', compact('sessions', 'tab', 'counts', 'formMembers', 'trainingTypes'));
    }

    public function storeSession(Request $request)
    {
        $trainer = auth()->user();
        $ids     = $this->service->myMemberIds($trainer, $trainer->gym_id);

        $data = $request->validate([
            'member_id'     => ['nullable', 'integer', 'in:' . $ids->implode(',')],
            'title'         => ['required', 'string', 'max:255'],
            'scheduled_at'  => ['required', 'date'],
            'duration_mins' => ['required', 'integer', 'min:15', 'max:480'],
            'session_type'  => ['required', 'in:personal,group'],
            'repeat_weeks'  => ['nullable', 'integer', 'min:1', 'max:12'],
            'notes'         => ['nullable', 'string', 'max:2000'],
        ], [
            'member_id.in'       => 'Please pick one of your assigned members.',
            'title.required'     => 'Please enter what this training is about (or pick a type).',
            'scheduled_at.required' => 'Please choose the date and time.',
        ]);

        if ($data['session_type'] === 'personal' && empty($data['member_id'])) {
            return back()->withInput()->withErrors(['member_id' => 'A personal session needs a member.']);
        }

        $weeks = (int) ($data['repeat_weeks'] ?? 1);
        $start = Carbon::parse($data['scheduled_at']);

        try {
            DB::transaction(function () use ($trainer, $data, $weeks, $start) {
                for ($w = 0; $w < $weeks; $w++) {
                    $this->service->createSession($trainer, $trainer->gym_id, array_merge($data, [
                        'scheduled_at' => $start->copy()->addWeeks($w),
                    ]));
                }
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', $weeks > 1 ? "{$weeks} weekly sessions scheduled." : 'Session scheduled.');
    }

    public function updateSessionStatus(Request $request, TrainingSession $session)
    {
        abort_unless((int) $session->trainer_id === (int) auth()->id(), 403);

        $data = $request->validate([
            'status' => ['required', 'in:scheduled,completed,cancelled,no_show'],
        ]);

        $session->update($data);

        $label = ['scheduled' => 'Session reopened.', 'completed' => 'Marked as done.', 'cancelled' => 'Session cancelled.', 'no_show' => 'Marked as no-show.'];

        return back()->with('success', $label[$data['status']]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function formMembers($ids)
    {
        return User::whereIn('id', $ids)->orderBy('name')->get(['id', 'name']);
    }
}
