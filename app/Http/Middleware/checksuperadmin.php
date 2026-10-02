<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class checksuperadmin
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() && ! Auth::user()->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin_login_form')->withErrors([
                'email' => 'Your account has been deactivated.',
            ]);
        }

        if (Auth::check() && ! hms_role_enabled(Auth::user()->roleSlug())) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin_login_form')->withErrors([
                'email' => 'This role is not available in the current institution mode.',
            ]);
        }

        return $next($request);
    }
}