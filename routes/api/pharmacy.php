<?php

use App\Http\Controllers\Api\V1\Admin\PharmacyController;
use Illuminate\Support\Facades\Route;

Route::get('/pharmacy/rx', [PharmacyController::class, 'index'])
    ->name('pharmacy.rx');

Route::post('/prescriptions/{prescription}/dispense', [PharmacyController::class, 'dispense'])
    ->name('prescriptions.dispense');
