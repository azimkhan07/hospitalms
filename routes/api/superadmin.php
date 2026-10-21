<?php

use App\Http\Controllers\Api\V1\SuperAdmin\AuditLogController;
use App\Http\Controllers\Api\V1\SuperAdmin\ErrorController;
use App\Http\Controllers\Api\V1\SuperAdmin\SnapshotController;
use App\Http\Controllers\Api\V1\SuperAdmin\TenantController;
use App\Http\Controllers\Api\V1\SuperAdmin\ThemeController;
use Illuminate\Support\Facades\Route;

Route::prefix('superadmin')
    ->name('superadmin.')
    ->middleware(['auth:sanctum', 'api.superadmin'])
    ->group(function () {
        Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
        Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');
        Route::get('/tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
        Route::put('/tenants/{tenant}', [TenantController::class, 'update'])->name('tenants.update');
        Route::delete('/tenants/{tenant}', [TenantController::class, 'destroy'])->name('tenants.destroy');

        Route::get('/errors', [ErrorController::class, 'index'])->name('errors.index');

        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

        Route::get('/snapshot', [SnapshotController::class, 'index'])->name('snapshot.index');

        Route::get('/themes', [ThemeController::class, 'index'])->name('themes.index');
        Route::get('/theme-payments', [ThemeController::class, 'payments'])->name('theme-payments.index');
        Route::post('/theme-payments', [ThemeController::class, 'store'])->name('theme-payments.store');
        Route::delete('/theme-payments/{payment}', [ThemeController::class, 'destroy'])->name('theme-payments.destroy');
    });
