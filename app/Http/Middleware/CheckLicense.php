<?php

namespace App\Http\Middleware;

use App\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckLicense
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app(LicenseService::class)->check()) {
            abort(403, 'License invalid or expired. Please contact the vendor to renew.');
        }

        return $next($request);
    }
}
