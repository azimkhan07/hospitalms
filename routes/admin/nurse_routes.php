<?php

use Illuminate\Support\Facades\Route;

Route::get('/nurse/drug-charts', App\Http\Livewire\Admins\NurseDrugCharts::class)->name('admin_nurse_drug_charts');
Route::get('/nurse/handovers', App\Http\Livewire\Admins\NurseHandovers::class)->name('admin_nurse_handovers');
