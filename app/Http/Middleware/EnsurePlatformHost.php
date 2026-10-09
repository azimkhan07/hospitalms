<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the platform (super admin) panel on the operator's own host(s).
 *
 * When HMS_PLATFORM_HOSTS is configured, the /admin login and the /superadmin
 * panel are only served from those hosts; every other host (a facility's
 * subdomain or a domain it purchased) is sent back to the platform login. When
 * the list is empty the lock is disabled, so single-host installs and local
 * development keep working exactly as before.
 */
class EnsurePlatformHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $hosts = (array) config('hms.platform_hosts', []);

        if (empty($hosts) || $this->allowed($request->getHost(), $hosts)) {
            return $next($request);
        }

        // Wrong host for the platform panel: hand the visitor to the operator.
        return redirect()->away($request->getScheme().'://'.$hosts[0].'/admin');
    }

    /**
     * @param  array<int, string>  $hosts
     */
    protected function allowed(string $host, array $hosts): bool
    {
        return in_array(strtolower($host), array_map('strtolower', $hosts), true);
    }
}
