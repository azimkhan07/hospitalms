<?php

/*
|--------------------------------------------------------------------------
| ICD-10 API
|--------------------------------------------------------------------------
| Coding lookup for the patient app + staff CRUD.
*/

use App\Http\Controllers\Api\V1\Admin\Icd10CodesController;
use Illuminate\Support\Facades\Route;

Route::get('/icd10', [Icd10CodesController::class, 'index'])->name('icd10');
Route::post('/icd10', [Icd10CodesController::class, 'store'])->name('icd10.store');
Route::delete('/icd10/{id}', [Icd10CodesController::class, 'destroy'])->name('icd10.destroy');