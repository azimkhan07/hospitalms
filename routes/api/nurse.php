<?php

use App\Http\Controllers\Api\V1\Admin\NurseClinicalController;
use Illuminate\Support\Facades\Route;

// Phase 6 nursing clinical module: drug chart + administrations, shift
// handover and the doctor alerts raised from the ward.
Route::prefix('nursing')->name('nursing.')->group(function () {
    Route::get('/drug-charts', [NurseClinicalController::class, 'drugChartsIndex'])->name('drug-charts');
    Route::post('/drug-charts', [NurseClinicalController::class, 'drugChartsStore'])->name('drug-charts.store');
    Route::get('/drug-charts/{chartId}/administrations', [NurseClinicalController::class, 'administrations'])
        ->name('drug-charts.administrations');
    Route::post('/drug-charts/{chartId}/administer', [NurseClinicalController::class, 'administer'])
        ->name('drug-charts.administer');
    Route::get('/handovers', [NurseClinicalController::class, 'handoversIndex'])->name('handovers');
    Route::post('/handovers', [NurseClinicalController::class, 'handoversStore'])->name('handovers.store');
    Route::get('/alerts', [NurseClinicalController::class, 'alertsIndex'])->name('alerts');
    Route::post('/alerts', [NurseClinicalController::class, 'alertsStore'])->name('alerts.store');
    Route::post('/alerts/{alertId}/ack', [NurseClinicalController::class, 'alertAck'])->name('alerts.ack');
});
