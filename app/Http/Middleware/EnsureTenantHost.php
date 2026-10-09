<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The mirror of {@see EnsurePlatformHost}: the facility panel and staff login
 * belong to the facility's own host. When the platform host(s) are configured,
 * a request for them that arrives on the operator's domain gets a 404 instead of
 * a half-broken tenant panel. Disabled when no platform hosts are set.
 */
class EnsureTenantHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $hosts = (array) config('hms.platform_hosts', []);

        if (empty($hosts)) {
            return $next($request);
        }

        $isPlatform = in_array(
            strtolower($request->getHost()),
            array_map('strtolower', $hosts),
            true
        );

        if ($isPlatform) {
            abort(404);
        }

        return $next($request);
    }
}
