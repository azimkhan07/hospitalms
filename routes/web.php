<?php

use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| FRONTEND ROUTES
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('index');
});

Route::get('/about', function () {
    return view('about');
});

Route::get('/contact', function () {
    return view('contact');
});

Route::get('/docters', function () {
    return view('docter');
});

Route::view('/services', 'services');

Route::get('/app', function () {
    return view('layouts.app');
});


/*
|--------------------------------------------------------------------------
| ADMIN AUTH ROUTES
|--------------------------------------------------------------------------
*/

Route::get('/admin', [AdminController::class, 'index'])->name('admin_login_form');

Route::post('/admin/login', [AdminController::class, 'authenticate_admin'])->name('admin_login');


/*
|--------------------------------------------------------------------------
| ADMIN PROTECTED ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'checksuperadmin'])->prefix('admin')->group(function () {

    Route::get('/dashboard', App\Http\Livewire\Admins\Dashboard::class)->name('admin_dashboard');

    Route::get('/settings', App\Http\Livewire\Admins\Settings::class)->name('admin_settings');

    Route::get('/nurses', App\Http\Livewire\Admins\Nurses::class)->name('nurses');

    Route::get('/operationsreport', App\Http\Livewire\Admins\Operationreport::class)->name('admin_operations_report');

    Route::get('/patients', App\Http\Livewire\Admins\Patients::class)->name('admin_patients');

    Route::get('/birthsreport', App\Http\Livewire\Admins\Birthreport::class)->name('admin_birth_report');

    Route::get('/patientBills', App\Http\Livewire\Admins\Bills::class)->name('patient_bills');

    Route::get('/rooms', App\Http\Livewire\Admins\Rooms::class)->name('rooms');

    Route::get('/beds', App\Http\Livewire\Admins\Beds::class)->name('patients_beds');

    Route::get('/medicinesStore', App\Http\Livewire\Admins\Medicinestore::class)->name('medicinesStore');

    Route::get('/departments', App\Http\Livewire\Admins\Departments::class)->name('departments');

    Route::get('/employees', App\Http\Livewire\Admins\Employees::class)->name('employees');

    Route::get('/appointment', App\Http\Livewire\Admins\Appiontment::class)->name('appointment');

    Route::get('/blocks', App\Http\Livewire\Admins\Blocks::class)->name('blocks');

    // ✅ FIXED (no duplicate admin)
    Route::get('/hods', App\Http\Livewire\Admins\Hods::class)->name('hods');

    Route::get('/requestedappointments', App\Http\Livewire\Admins\RequestedAppointments::class)->name('requestedAppointment');

    Route::get('/subscribers', App\Http\Livewire\Admins\Subscibers::class)->name('subscibers');

    Route::get('/contactedus', App\Http\Livewire\Admins\Contactedus::class)->name('contactedus');

});


/*
|--------------------------------------------------------------------------
| AUTH (Laravel default)
|--------------------------------------------------------------------------
*/

Auth::routes();