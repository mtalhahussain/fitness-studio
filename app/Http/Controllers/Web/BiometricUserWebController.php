<?php

namespace App\Http\Controllers\Web;

use App\Biometric\BiometricCode;
use App\Biometric\DeviceCommandQueue;
use App\Http\Controllers\Controller;
use App\Models\DeviceCommand;
use App\Models\User;

/** Machine PIN of a member/trainer: view, push to the gym's machines, regenerate. */
class BiometricUserWebController extends Controller
{
    public function __construct(
        private BiometricCode $codes,
        private DeviceCommandQueue $queue,
    ) {}

    public function show(User $user)
    {
        $this->authorizeUser($user);

        $commands = DeviceCommand::where('user_id', $user->id)
            ->latest('id')->limit(10)
            ->get(['device_serial_number', 'command', 'status', 'result', 'updated_at']);

        return response()->json(['biometric_code' => $user->biometric_code, 'commands' => $commands]);
    }

    /** Assign a PIN if missing and (re)send the user to every active machine of the gym. */
    public function push(User $user)
    {
        $this->authorizeUser($user);

        $code   = $this->codes->ensure($user);
        $queued = $this->queue->pushUser($user);

        return response()->json(['ok' => true, 'biometric_code' => $code, 'queued' => $queued]);
    }

    /** New PIN: the old one is deleted from the machines (with its fingerprints), so the user must re-enroll. */
    public function regenerate(User $user)
    {
        $this->authorizeUser($user);

        $old  = $user->biometric_code;
        $code = $this->codes->regenerate($user);

        if ($old) {
            $this->queue->removePin($user->gym_id, $old, $user->id);
        }
        $queued = $this->queue->pushUser($user);

        return response()->json(['ok' => true, 'biometric_code' => $code, 'previous_code' => $old, 'queued' => $queued]);
    }

    private function authorizeUser(User $user): void
    {
        $auth  = auth()->user();
        $gymId = $auth->isAdmin() ? (int) session('admin_active_gym_id') : $auth->gym_id;

        abort_if(! $user->hasAnyRole(['member', 'trainer']), 404);
        abort_if($gymId && $user->gym_id !== $gymId, 403);
    }
}
