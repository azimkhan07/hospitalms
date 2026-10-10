<?php

/*
|--------------------------------------------------------------------------
| LAB BARCODE SLIP
|--------------------------------------------------------------------------
| Printable sample barcode slip for a collected lab sample.
*/

use Illuminate\Support\Facades\Route;

Route::get('/lab/barcode/{report}', [App\Http\Controllers\Admin\LabBarcodeController::class, 'show'])
    ->name('admin_lab_barcode_print');