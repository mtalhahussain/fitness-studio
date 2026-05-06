<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Membership;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $service) {}

    public function index(Request $request)
    {
        $gymId = auth()->user()->gym_id;

        // Core KPIs from service (single optimised query, cached 60s)
        $coreStats = $this->service->getStats($gymId);

        // Web-only extras (not in API stats)
        $stats = array_merge($coreStats, [
            'total_trainers'    => User::trainers()->forGym($gymId)->count(),
            'upcoming_sessions' => TrainingSession::forGym($gymId)->upcoming()->count(),
            'expiring_soon'     => Membership::forGym($gymId)
                ->where('status', 'active')
                ->whereBetween('end_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
                ->count(),
        ]);

        $recentMembers   = User::members()->forGym($gymId)->with('activeMembership.plan')->latest()->limit(6)->get();
        $todayAttendance = Attendance::forGym($gymId)->today()->with('user:id,name,email')->latest('check_in_time')->limit(8)->get();

        return view('dashboard', compact('stats', 'recentMembers', 'todayAttendance'));
    }
}
