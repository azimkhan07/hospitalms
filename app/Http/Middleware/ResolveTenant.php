<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves which facility a request belongs to from the HTTP host, then makes
 * it available as Tenant::current() for branding, scoping and host-specific
 * behaviour. Single-host installs fall back to config('hms.host_map').
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolve($request);

        Tenant::setCurrent($tenant);

        $response = $next($request);

        Tenant::setCurrent(null);

        return $response;
    }

    protected function resolve(Request $request): ?Tenant
    {
        $host = strtolower($request->getHost());

        $map = (array) config('hms.host_map', []);
        $mappedId = $map[$host] ?? null;

        if ($mappedId) {
            return Tenant::query()->withoutGlobalScopes()->whereKey($mappedId)->where('status', 'active')->first();
        }

        return Tenant::query()->withoutGlobalScopes()->where('status', 'active')
            ->where(fn ($q) => $q->whereRaw('LOWER(domain) = ?', [$host])->orWhereRaw('LOWER(subdomain) = ?', [$host]))
            ->first();
    }
}