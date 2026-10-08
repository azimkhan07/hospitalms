<?php

/*
|--------------------------------------------------------------------------
| TENANT ADMIN / STAFF PANEL ROUTES
|--------------------------------------------------------------------------
| All routes require an authenticated, enabled staff user. Livewire
| components are mounted at /admin/{section}.
*/

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'checksuperadmin'])->prefix('admin')->group(function () {

    Route::get('/dashboard', App\Http\Livewire\Admins\Dashboard::class)->name('admin_dashboard');

    Route::get('/reports', App\Http\Livewire\Admins\Reports::class)->name('admin_reports');

    Route::get('/reports/download', [App\Http\Controllers\Admin\ReportsExportController::class, 'csv'])->name('admin_reports_download');

    Route::get('/facilities', App\Http\Livewire\Admins\Facilities::class)->name('admin_facilities');

    Route::get('/settings', App\Http\Livewire\Admins\Settings::class)->name('admin_settings');

    Route::get('/meetings', App\Http\Livewire\Admins\MeetingCalendar::class)->name('admin_meetings');

    Route::get('/calendar', App\Http\Livewire\Admins\Events::class)->name('admin_calendar');

    Route::get('/deliveries', App\Http\Livewire\Admins\Deliveries::class)->name('admin_deliveries');

    Route::get('/leave', App\Http\Livewire\Admins\LeaveRequests::class)->name('admin_leave');

    Route::get('/attendance', App\Http\Livewire\Admins\AttendanceRegister::class)->name('admin_attendance');

    Route::get('/attendance/report', App\Http\Livewire\Admins\AttendanceReport::class)->name('admin_attendance_report');

    Route::get('/staff', App\Http\Livewire\Admins\StaffDirectory::class)->name('admin_staff');

    Route::get('/prescriptions', App\Http\Livewire\Admins\Prescriptions::class)->name('admin_prescriptions');

    Route::get('/pharmacy', App\Http\Livewire\Admins\Pharmacy::class)->name('admin_pharmacy');

    Route::get('/patient-history', App\Http\Livewire\Admins\PatientHistory::class)->name('admin_history');

    Route::get('/discharges', App\Http\Livewire\Admins\DischargeHistory::class)->name('admin_discharges');

    Route::get('/expired-medicines', App\Http\Livewire\Admins\ExpiredMedicines::class)->name('admin_expired_medicines');

    Route::get('/nurses', App\Http\Livewire\Admins\Nurses::class)->name('nurses');

    Route::get('/operationsreport', App\Http\Livewire\Admins\Operationreport::class)->name('admin_operations_report');

    Route::get('/patients', App\Http\Livewire\Admins\Patients::class)->name('admin_patients');

    Route::get('/birthsreport', App\Http\Livewire\Admins\Birthreport::class)->name('admin_birth_report');

    Route::get('/patientBills', App\Http\Livewire\Admins\Bills::class)->name('patient_bills');

    Route::get('/accounting', App\Http\Livewire\Admins\Accounts::class)->name('admin_accounting');

    Route::get('/rooms', App\Http\Livewire\Admins\Rooms::class)->name('rooms');

    Route::get('/beds', App\Http\Livewire\Admins\Beds::class)->name('patients_beds');

    Route::get('/machines', App\Http\Livewire\Admins\Machines::class)->name('admin_machines');

    Route::get('/angio-machines', App\Http\Livewire\Admins\AngioMachines::class)->name('admin_angio_machines');

    Route::get('/schemes', App\Http\Livewire\Admins\Schemes::class)->name('admin_schemes');

    Route::get('/investigations', App\Http\Livewire\Admins\Investigations::class)->name('admin_investigations');

    Route::get('/bedreports', App\Http\Livewire\Admins\BedReports::class)->name('admin_bed_reports');

    Route::get('/medicinesStore', App\Http\Livewire\Admins\Medicinestore::class)->name('medicinesStore');

    Route::get('/departments', App\Http\Livewire\Admins\Departments::class)->name('departments');

    Route::get('/employees', App\Http\Livewire\Admins\Employees::class)->name('employees');

    Route::get('/appointment', App\Http\Livewire\Admins\Appiontment::class)->name('appointment');

    Route::get('/blocks', App\Http\Livewire\Admins\Blocks::class)->name('blocks');

    Route::get('/hods', App\Http\Livewire\Admins\Hods::class)->name('hods');

    Route::get('/requestedappointments', App\Http\Livewire\Admins\RequestedAppointments::class)->name('requestedAppointment');

    Route::get('/subscribers', App\Http\Livewire\Admins\Subscibers::class)->name('subscibers');

    Route::get('/contactedus', App\Http\Livewire\Admins\Contactedus::class)->name('contactedus');

    Route::get('/print-center', [App\Http\Controllers\Admin\PrintController::class, 'index'])->name('admin_print_center');

    Route::get('/prints/invoice/{bill}', [App\Http\Controllers\Admin\PrintController::class, 'invoice'])->name('admin_print_invoice');

    Route::get('/prints/medicine-slip/{patient}', [App\Http\Controllers\Admin\PrintController::class, 'medicineSlip'])->name('admin_print_medicine_slip');

    Route::get('/prints/case-paper/{patient}', [App\Http\Controllers\Admin\PrintController::class, 'casePaper'])->name('admin_print_case_paper');

    Route::get('/prints/prescription/{prescription}', [App\Http\Controllers\Admin\PrintController::class, 'prescription'])->name('admin_print_prescription');

});
