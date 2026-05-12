<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireGymContext
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if ($user && $user->isAdmin() && ! session('admin_active_gym_id')) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'No gym context selected. Switch to a gym first.'], 403);
            }

            return redirect()->route('dashboard')
                ->with('warning', 'Switch to a gym context first to access this section.');
        }

        return $next($request);
    }
}
