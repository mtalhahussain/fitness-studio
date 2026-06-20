<?php

namespace App\Http\Middleware;

use App\GymContext;
use App\Models\Gym;
use App\Services\GymDomainResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GymTenantMiddleware
{
    public function __construct(
        private GymContext $context,
        private GymDomainResolver $domainResolver,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (! $user) {
            return $next($request);
        }

        if ($user->isAdmin()) {
            // Admin: use session-stored gym context (set via gym switcher)
            $activeGymId = session('admin_active_gym_id');

            $hostGym = $this->domainResolver->resolveByHost($request->getHost());
            if ($hostGym) {
                $activeGymId = (int) $hostGym->id;
                session(['admin_active_gym_id' => $activeGymId]);
            }

            if ($activeGymId) {
                $this->context->set((int) $activeGymId);
            }
            return $next($request);
        }

        if (! $user->gym_id) {
            abort(403, 'No gym assigned to this account.');
        }

        $hostGym = $this->domainResolver->resolveByHost($request->getHost());

        if ($hostGym && (int) $hostGym->id !== (int) $user->gym_id) {
            abort(403, 'This account is not linked to this gym domain.');
        }

        $gym = $hostGym ?: Gym::find($user->gym_id);

        if (! $gym) {
            abort(403, 'Gym not found.');
        }

        if (! $gym->isActive()) {
            abort(403, 'Your gym account is suspended or inactive. Please contact the platform administrator.');
        }

        $this->context->set($user->gym_id);

        return $next($request);
    }
}
