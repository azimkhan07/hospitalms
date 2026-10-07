<?php

/*
|--------------------------------------------------------------------------
| SUPER ADMIN (platform owner) ROUTES
|--------------------------------------------------------------------------
| Login is intentionally kept on the generic /admin URL (separate from the
| staff /login). The panel itself lives under /superadmin.
*/

use App\Http\Controllers\SuperAdminController;
use Illuminate\Support\Facades\Route;

Route::name('superadmin.')->group(function () {

    Route::get('/admin', [SuperAdminController::class, 'showLogin'])
        ->middleware('guest')->name('login');

    Route::post('/admin/login', [SuperAdminController::class, 'login'])
        ->middleware('guest')->name('login.attempt');

    Route::post('/admin/logout', [SuperAdminController::class, 'logout'])
        ->middleware('auth')->name('logout');

});

Route::prefix('superadmin')->name('superadmin.')->middleware('superadmin')->group(function () {

    Route::get('/', fn () => redirect()->route('superadmin.dashboard'));

    Route::get('/dashboard', [SuperAdminController::class, 'dashboard'])->name('dashboard');

    Route::get('/tenants', [SuperAdminController::class, 'tenants'])->name('tenants');

    Route::get('/tenant-admins', [SuperAdminController::class, 'admins'])->name('admins');

    Route::get('/errors', [SuperAdminController::class, 'errors'])->name('errors');

    Route::get('/audit-logs', [SuperAdminController::class, 'auditLogs'])->name('audit-logs');

});
