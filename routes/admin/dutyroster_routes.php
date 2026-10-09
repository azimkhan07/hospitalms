<?php

/*
|--------------------------------------------------------------------------
| DUTY ROSTER
|--------------------------------------------------------------------------
| Weekly duty schedule for the medical staff: doctor x shift per date.
*/

use Illuminate\Support\Facades\Route;

Route::get('/duty-roster', App\Http\Livewire\Admins\DutyRoster::class)->name('admin_duty_roster');