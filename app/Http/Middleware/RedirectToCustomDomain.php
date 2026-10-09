<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * One canonical address per facility.
 *
 * When a facility has moved onto a domain it purchased, its old auto-generated
 * address ({slug}.{base_domain}) must not keep serving a second copy of the
 * site. A request arriving on that generated host is 301-redirected to the
 * custom domain. The host_map aliases used by single-host installs
 * (127.0.0.1, localhost) are never redirected.
 */
class RedirectToCustomDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Tenant::current();

        if (! $tenant || ! $tenant->domain || ! $tenant->subdomain) {
            return $next($request);
        }

        $host = strtolower($request->getHost());

        // Only the facility's own generated address redirects; anything else
        // (the custom domain itself, the platform host, an unknown host) passes.
        if ($host !== strtolower($tenant->subdomain)) {
            return $next($request);
        }

        $aliases = array_map('strtolower', array_keys((array) config('hms.host_map', [])));

        if (in_array($host, $aliases, true)) {
            return $next($request);
        }

        return redirect()->away(
            $request->getScheme().'://'.$tenant->domain.$request->getRequestUri(),
            301
        );
    }
}
