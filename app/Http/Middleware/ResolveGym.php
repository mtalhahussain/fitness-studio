<?php

namespace App\Http\Middleware;

use App\GymContext;
use App\Models\Gym;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ResolveGym
{
    public function __construct(private GymContext $context) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $user = Auth::user();

        if (! $user) {
            return $next($request);
        }

        // Super-admin sees all gyms — context stays null, no scope applied
        if ($user->isAdmin()) {
            return $next($request);
        }

        if (! $user->gym_id) {
            abort(403, 'No gym assigned to this account.');
        }

        $gym = Gym::find($user->gym_id);

        if (! $gym) {
            abort(403, 'Gym not found.');
        }

        if (! $gym->isActive()) {
            abort(403, 'Gym account is suspended or inactive.');
        }

        $this->context->set($user->gym_id);

        return $next($request);
    }
}
