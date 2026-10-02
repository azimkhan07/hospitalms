<?php

use App\Http\Controllers\Api\V1\AppointmentController;
use Illuminate\Support\Facades\Route;

Route::get('/appointments/doctors', [AppointmentController::class, 'doctors'])
    ->name('appointments.doctors');

Route::post('/appointments/request', [AppointmentController::class, 'request'])
    ->name('appointments.request');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/appointments/requested', [AppointmentController::class, 'requested'])
        ->name('appointments.requested');
});
