<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MemberTrainingService;
use App\Services\TrainerCommissionService;
use Illuminate\Http\Request;

class TrainerCommissionWebController extends Controller
{
    public function __construct(
        private TrainerCommissionService $commissionService,
        private MemberTrainingService $trainingService,
    ) {}

    public function overview(Request $request, User $trainer)
    {
        $user  = auth()->user();
        $gymId = $user->isTrainer() ? $user->gym_id : ($user->isAdmin() ? (int) session('admin_active_gym_id') : $user->gym_id);
        $canManageCommission = $user->isOwner();

        if ($user->isTrainer() && $user->id !== $trainer->id) {
            abort(403, 'You can only view your own commission.');
        }

        $month = $request->get('month', now()->format('Y-m'));

        $earnings    = $this->commissionService->getTrainerEarnings($trainer->id, $gymId, $month);
        $monthly     = $this->commissionService->getMonthlyBreakdown($trainer->id, $gymId);
        $memberStats = $this->trainingService->getTrainerMemberSummary($trainer, $gymId);
        $currentRate = $this->commissionService->getCommissionRate($gymId, $trainer->id);
        $members     = $this->trainingService->getMembersByTrainer($trainer, $gymId);

        return view('trainers.commission', compact(
            'trainer', 'earnings', 'monthly', 'memberStats', 'currentRate', 'month', 'members', 'canManageCommission'
        ));
    }

    public function report(Request $request)
    {
        $gymId    = auth()->user()->gym_id;
        $month    = $request->get('month', now()->format('Y-m'));

        $report   = $this->commissionService->getCommissionReport($gymId, $month);
        $split    = $this->commissionService->getGymVsTrainerSplit($gymId, $month);
        $configs  = $this->commissionService->getConfigs($gymId);
        $trainers = User::trainers()->forGym($gymId)->with('trainerProfile')->get(['id', 'name', 'email']);

        return view('reports.commissions', compact('report', 'split', 'month', 'trainers', 'configs'));
    }

    public function setConfig(Request $request)
    {
        abort_unless(auth()->user()?->isOwner(), 403, 'Only owner can manage commission rates.');

        $data = $request->validate([
            'trainer_id'      => ['nullable', 'integer', 'exists:users,id'],
            'commission_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'effective_from'  => ['required', 'date'],
            'effective_to'    => ['nullable', 'date', 'after:effective_from'],
        ]);

        $gymId = auth()->user()->gym_id;
        $config = $this->commissionService->setCommissionConfig($gymId, $data);

        return response()->json(['message' => 'Commission rate saved.', 'config' => $config]);
    }

    public function trainerEarnings(Request $request, User $trainer)
    {
        $user  = auth()->user();
        if ($user->isTrainer() && $user->id !== $trainer->id) {
            abort(403);
        }
        $gymId   = $user->isTrainer() ? $user->gym_id : ($user->isAdmin() ? (int) session('admin_active_gym_id') : $user->gym_id);
        $month   = $request->get('month');
        $earnings = $this->commissionService->getTrainerEarnings($trainer->id, $gymId, $month);
        $monthly  = $this->commissionService->getMonthlyBreakdown($trainer->id, $gymId);

        return response()->json(compact('earnings', 'monthly'));
    }
}
