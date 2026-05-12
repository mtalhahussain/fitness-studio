<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Gym;
use App\Models\Invoice;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\TrainerCommission;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $service) {}

    public function index(Request $request)
    {
        $user = auth()->user();

        if ($user->isAdmin() && ! session('admin_active_gym_id')) {
            return $this->adminPlatformDashboard();
        }

        $gymId = $user->isAdmin()
            ? (int) session('admin_active_gym_id')
            : $user->gym_id;

        if ($user->isMember()) {
            return $this->memberDashboard($user, $gymId);
        }

        if ($user->isTrainer()) {
            return $this->trainerDashboard($user, $gymId);
        }

        return $this->gymDashboard($gymId);
    }

    // ── Admin Platform Overview ───────────────────────────────────────────────

    private function adminPlatformDashboard()
    {
        $totalGyms    = Gym::count();
        $activeGyms   = Gym::where('status', 'active')->count();
        $totalMembers = User::members()->count();
        $totalTrainers= User::trainers()->count();

        $monthlyRevenue = Payment::whereYear('paid_at', now()->year)
            ->whereMonth('paid_at', now()->month)
            ->sum('amount');

        $totalRevenue = Payment::sum('amount');

        $gyms = Gym::withCount([
                'users as members_count' => fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', 'member')),
                'users as trainers_count'=> fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', 'trainer')),
            ])
            ->with('owner:id,name,email,gym_id')
            ->latest()
            ->get();

        return view('dashboard-admin', compact(
            'totalGyms', 'activeGyms', 'totalMembers',
            'totalTrainers', 'monthlyRevenue', 'totalRevenue', 'gyms'
        ));
    }

    // ── Member Dashboard ─────────────────────────────────────────────────────

    private function memberDashboard(User $user, int $gymId)
    {
        $membership = Membership::forUser($user->id)
            ->where('memberships.status', 'active')
            ->with('plan')
            ->first();

        $monthCheckins = Attendance::where('user_id', $user->id)
            ->whereMonth('check_in_time', now()->month)
            ->whereYear('check_in_time', now()->year)
            ->count();

        $recentAttendance = Attendance::where('user_id', $user->id)
            ->latest('check_in_time')
            ->limit(8)
            ->get();

        $upcomingSessions = TrainingSession::where('member_id', $user->id)
            ->where('scheduled_at', '>=', now())
            ->where('status', 'scheduled')
            ->with('trainer:id,name')
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get();

        $todayRecord = Attendance::where('user_id', $user->id)
            ->whereDate('check_in_time', today())
            ->latest('check_in_time')
            ->first();

        $invoices = Invoice::where('user_id', $user->id)
            ->with('payments')
            ->latest()
            ->limit(6)
            ->get();

        return view('dashboard-member', compact(
            'membership', 'monthCheckins', 'recentAttendance', 'upcomingSessions', 'todayRecord', 'invoices'
        ));
    }

    // ── Trainer Dashboard ─────────────────────────────────────────────────────

    private function trainerDashboard(User $user, int $gymId)
    {
        $assignedCount = DB::table('trainer_member')
            ->where('trainer_id', $user->id)
            ->where('gym_id', $gymId)
            ->where('is_active', true)
            ->count();

        $sessionsToday = TrainingSession::where('trainer_id', $user->id)
            ->whereDate('scheduled_at', today())
            ->count();

        $sessionsThisMonth = TrainingSession::where('trainer_id', $user->id)
            ->whereMonth('scheduled_at', now()->month)
            ->whereYear('scheduled_at', now()->year)
            ->where('status', 'completed')
            ->count();

        $monthEarnings = TrainerCommission::where('trainer_id', $user->id)
            ->where('gym_id', $gymId)
            ->where('period_month', now()->startOfMonth()->toDateString())
            ->sum('trainer_share');

        $upcomingSessions = TrainingSession::where('trainer_id', $user->id)
            ->where('scheduled_at', '>=', now())
            ->where('status', 'scheduled')
            ->with('member:id,name,email')
            ->orderBy('scheduled_at')
            ->limit(8)
            ->get();

        $assignedMembers = User::members()
            ->whereHas('trainerPeriods', fn ($q) => $q
                ->where('trainer_id', $user->id)
                ->where('status', 'active')
            )
            ->with('activeMembership.plan')
            ->get();

        return view('dashboard-trainer', compact(
            'assignedCount', 'sessionsToday', 'sessionsThisMonth',
            'monthEarnings', 'upcomingSessions', 'assignedMembers', 'user'
        ));
    }

    // ── Gym-Specific Dashboard ────────────────────────────────────────────────

    private function gymDashboard(?int $gymId)
    {
        $coreStats = $this->service->getStats($gymId);

        $stats = array_merge($coreStats, [
            'total_trainers'    => User::trainers()->forGym($gymId)->count(),
            'upcoming_sessions' => TrainingSession::forGym($gymId)->upcoming()->count(),
            'expiring_soon'     => Membership::forGym($gymId)
                ->where('memberships.status', 'active')
                ->whereBetween('memberships.end_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
                ->count(),
        ]);

        $recentMembers   = User::members()->forGym($gymId)->with('activeMembership.plan')->latest()->limit(6)->get();
        $todayAttendance = Attendance::forGym($gymId)->today()->with('user:id,name,email')->latest('check_in_time')->get();

        $paymentsDue = Membership::forGym($gymId)
            ->where('memberships.status', 'active')
            ->join('membership_plans', 'memberships.plan_id', '=', 'membership_plans.id')
            ->whereRaw('memberships.amount_paid < membership_plans.price')
            ->select('memberships.*')
            ->with(['user:id,name,email,phone', 'plan:id,name,type,price'])
            ->orderByRaw('(membership_plans.price - memberships.amount_paid) DESC')
            ->get();

        return view('dashboard', compact('stats', 'recentMembers', 'todayAttendance', 'paymentsDue'));
    }
}
