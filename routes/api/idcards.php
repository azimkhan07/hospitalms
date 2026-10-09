<?php

/*
|--------------------------------------------------------------------------
| STAFF ID CARDS API (hospital mode only)
|--------------------------------------------------------------------------
| Mobile mirror of the hospital ID-card workflow. The endpoint resolves
| the signed-in tenant itself, so no tenant_id needs to cross the wire.
*/

use App\Http\Controllers\Api\V1\Admin\IdCardsController;
use Illuminate\Support\Facades\Route;

Route::get('/id-cards', [IdCardsController::class, 'index'])->name('id-cards');