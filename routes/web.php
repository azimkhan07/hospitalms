<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| FRONTEND ROUTES
|--------------------------------------------------------------------------
*/

Route::get('/', [SiteController::class, 'index'])->name('site.index');

Route::get('/about', [SiteController::class, 'about'])->name('site.about');

Route::get('/contact', [SiteController::class, 'contact'])->name('site.contact');

Route::get('/docters', [SiteController::class, 'doctors'])->name('site.doctors');

Route::get('/services', [SiteController::class, 'services'])->name('site.services');


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

    Route::get('/meetings', App\Http\Livewire\Admins\MeetingCalendar::class)->name('admin_meetings');

    Route::get('/leave', App\Http\Livewire\Admins\LeaveRequests::class)->name('admin_leave');

    Route::get('/staff', App\Http\Livewire\Admins\StaffDirectory::class)->name('admin_staff');

    Route::get('/prescriptions', App\Http\Livewire\Admins\Prescriptions::class)->name('admin_prescriptions');

    Route::get('/patient-history', App\Http\Livewire\Admins\PatientHistory::class)->name('admin_history');

    Route::get('/discharges', App\Http\Livewire\Admins\DischargeHistory::class)->name('admin_discharges');

    Route::get('/expired-medicines', App\Http\Livewire\Admins\ExpiredMedicines::class)->name('admin_expired_medicines');

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