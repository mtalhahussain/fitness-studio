<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GymTenantMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user && ! $user->isAdmin() && ! $user->gym_id) {
            abort(403, 'No gym assigned to this account.');
        }

        if ($user && $user->gym_id) {
            app()->instance('current_gym_id', $user->gym_id);
        }

        return $next($request);
    }
}
