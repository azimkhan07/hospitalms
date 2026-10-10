<?php

/*
|--------------------------------------------------------------------------
| ICD-10 DIAGNOSIS CODES
|--------------------------------------------------------------------------
| Medical coding directory + per-consult ICD code on the doctor's desk.
*/

use Illuminate\Support\Facades\Route;

Route::get('/icd10', App\Http\Livewire\Admins\Icd10::class)->name('admin_icd10');