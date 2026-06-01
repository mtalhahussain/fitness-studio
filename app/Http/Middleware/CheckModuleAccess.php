<?php

namespace App\Http\Middleware;

use App\GymContext;
use App\Models\Gym;
use Closure;
use Illuminate\Http\Request;

class CheckModuleAccess
{
    public function __construct(private GymContext $context) {}

    public function handle(Request $request, Closure $next, string $module): mixed
    {
        // Super admin always has access to everything
        $user = auth()->user();
        if ($user && $user->isAdmin()) {
            return $next($request);
        }

        $gymId = $this->context->id();

        if (! $gymId) {
            return $next($request);
        }

        $gym = Gym::find($gymId);

        if (! $gym || ! $gym->hasModule($module)) {
            $label = config("modules.available.{$module}.label", ucfirst($module));
            abort(403, "The \"{$label}\" module is not enabled for your gym. Please contact your administrator.");
        }

        return $next($request);
    }
}
