<?php

use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\ResourceController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth:sanctum', 'api.staff'])
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/patients', [ResourceController::class, 'patients'])->name('patients');
        Route::get('/appointments', [ResourceController::class, 'appointments'])->name('appointments');
        Route::get('/medicines', [ResourceController::class, 'medicines'])->name('medicines');
        Route::get('/staff', [ResourceController::class, 'staff'])->name('staff');
    });
