<?php

namespace App\Http\Middleware;

use App\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckLicense
{
    /** Machine traffic: a license problem must not make devices drop punches with a 403. */
    private const DEVICE_PATHS = ['iclock/*', 'api/biometric/push', 'api/biometric/iclock/*', 'api/biometric/hook/*'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is(...self::DEVICE_PATHS)) {
            return $next($request);
        }

        if (! app(LicenseService::class)->check()) {
            abort(403, 'License invalid or expired. Please contact the vendor to renew.');
        }

        return $next($request);
    }
}
