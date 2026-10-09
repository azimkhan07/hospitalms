<?php

/*
|--------------------------------------------------------------------------
| DUTY ROSTER API
|--------------------------------------------------------------------------
| Mobile mirror of the duty roster workflow.
*/

use App\Http\Controllers\Api\V1\Admin\DutyRostersController;
use Illuminate\Support\Facades\Route;

Route::get('/duty-rosters', [DutyRostersController::class, 'index'])->name('duty-rosters');
Route::post('/duty-rosters', [DutyRostersController::class, 'store'])->name('duty-rosters.store');
Route::delete('/duty-rosters/{id}', [DutyRostersController::class, 'destroy'])->name('duty-rosters.destroy');