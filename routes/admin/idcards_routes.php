<?php

/*
|--------------------------------------------------------------------------
| STAFF ID CARDS (hospital mode only)
|--------------------------------------------------------------------------
| The batch ID-card workflow only exists for full multi-speciality
| hospitals; the panel and the print route are gated on
| hms_idcards_enabled() (see app/helpers.php) plus the staff module.
*/

use Illuminate\Support\Facades\Route;

Route::get('/id-cards', App\Http\Livewire\Admins\IdCards::class)->name('admin_id_cards');

Route::get('/id-cards/print/{user?}', [App\Http\Controllers\Admin\IdCardController::class, 'print'])
    ->name('admin_id_cards_print');