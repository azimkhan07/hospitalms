<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active || ! $user->roleSlug() || ! $user->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'An active tenant staff account is required.',
            ], 403);
        }

        return $next($request);
    }
}
