<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next, ?string $module = null)
    {
        if ($module === null) {
            return $next($request);
        }

        abort_unless(hms_can($module), 403);

        return $next($request);
    }
}