<?php

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureApiStaff;
use App\Http\Middleware\EnsureApiSuperAdmin;
use App\Http\Middleware\EnsurePlatformHost;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureTenantHost;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\RedirectToCustomDomain;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\checksuperadmin;
use App\Services\ErrorLogger;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance']);

        $middleware->web(append: [
            AuthenticateSession::class,
            ResolveTenant::class,
            RedirectToCustomDomain::class,
        ]);

        $middleware->alias([
            'auth' => Authenticate::class,
            'auth.basic' => Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
            'api.staff' => EnsureApiStaff::class,
            'api.superadmin' => EnsureApiSuperAdmin::class,
            'cache.headers' => SetCacheHeaders::class,
            'can' => Authorize::class,
            'checksuperadmin' => checksuperadmin::class,
            'guest' => RedirectIfAuthenticated::class,
            'password.confirm' => RequirePassword::class,
            'platform.host' => EnsurePlatformHost::class,
            'signed' => ValidateSignature::class,
            'superadmin' => EnsureSuperAdmin::class,
            'tenant.host' => EnsureTenantHost::class,
            'throttle' => ThrottleRequests::class,
            'verified' => EnsureEmailIsVerified::class,
        ]);

        $middleware->api(append: [
            ResolveTenant::class,
        ]);

        $middleware->trustProxies(at: '*');

        // The host locks must run before any Auth:: middleware: Laravel's default
        // priority map sorts every AuthenticatesRequests implementation ahead of
        // the rest of the stack (via that interface), so a redirect to /login
        // would otherwise win over our 404/redirect on the wrong host.
        $middleware->prependToPriorityList(
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            \App\Http\Middleware\EnsurePlatformHost::class
        );
        $middleware->prependToPriorityList(
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            \App\Http\Middleware\EnsureTenantHost::class
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (Throwable $e) {
            app(ErrorLogger::class)->log($e);
        });
    })->create();