<?php

use App\Http\Controllers\Api\V1\Admin\BillingController;
use Illuminate\Support\Facades\Route;

Route::get('/bills/{bill}/items', [BillingController::class, 'items'])
    ->name('bills.items');

Route::post('/bills/{bill}/items', [BillingController::class, 'storeItem'])
    ->name('bills.items.store');

Route::delete('/bills/{bill}/items/{item}', [BillingController::class, 'destroyItem'])
    ->name('bills.items.destroy');

Route::post('/bills/{bill}/finalise-stay', [BillingController::class, 'finaliseStay'])
    ->name('bills.finalise-stay');

Route::patch('/bills/{bill}', [BillingController::class, 'update'])
    ->name('bills.update');

Route::get('/day-book', [BillingController::class, 'dayBook'])
    ->name('day-book');
