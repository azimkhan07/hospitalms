<?php

use App\Http\Controllers\Api\V1\Admin\AccountingController;
use App\Http\Controllers\Api\V1\Admin\AngioMachinesController;
use App\Http\Controllers\Api\V1\Admin\CalendarEventsController;
use App\Http\Controllers\Api\V1\Admin\ClinicalController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\DeliveriesController;
use App\Http\Controllers\Api\V1\Admin\FacilitiesController;
use App\Http\Controllers\Api\V1\Admin\InvestigationsController;
use App\Http\Controllers\Api\V1\Admin\MachinesController;
use App\Http\Controllers\Api\V1\Admin\MeetingsController;
use App\Http\Controllers\Api\V1\Admin\PrintController;
use App\Http\Controllers\Api\V1\Admin\QueueController;
use App\Http\Controllers\Api\V1\Admin\ReportsController;
use App\Http\Controllers\Api\V1\Admin\ResourceController;
use App\Http\Controllers\Api\V1\Admin\SchemesController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth:sanctum', 'api.staff'])
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/patients', [ResourceController::class, 'patients'])->name('patients');
        Route::get('/appointments', [ResourceController::class, 'appointments'])->name('appointments');
        Route::get('/medicines', [ResourceController::class, 'medicines'])->name('medicines');
        Route::get('/staff', [ResourceController::class, 'staff'])->name('staff');
        Route::get('/accounting', [AccountingController::class, 'summary'])->name('accounting');
        Route::get('/reports', [ReportsController::class, 'index'])->name('reports');
        Route::get('/angio-machines', [AngioMachinesController::class, 'index'])->name('angio-machines');
        Route::get('/schemes', [SchemesController::class, 'index'])->name('schemes');
        Route::get('/calendar-events', [CalendarEventsController::class, 'index'])->name('calendar-events');

        // Video meetings + dashboard newsletter (PLAN.md 18g).
        Route::get('/meetings', [MeetingsController::class, 'index'])->name('meetings');
        Route::post('/meetings/{meetingId}/join', [MeetingsController::class, 'join'])->name('meetings.join');
        Route::get('/newsletters', [MeetingsController::class, 'newsletters'])->name('newsletters');
        Route::get('/deliveries', [DeliveriesController::class, 'index'])->name('deliveries');
        Route::post('/deliveries', [DeliveriesController::class, 'store'])->name('deliveries.create');
        Route::get('/print-center', [PrintController::class, 'index'])->name('print-center');
        Route::get('/facilities', [FacilitiesController::class, 'index'])->name('facilities');
        Route::get('/machines', [MachinesController::class, 'index'])->name('machines');
        Route::get('/investigations', [InvestigationsController::class, 'index'])->name('investigations');

        // Clinical workflow: vitals, IPD ward, lab queue, OPD status moves.
        Route::get('/vitals', [ClinicalController::class, 'vitalsIndex'])->name('vitals');
        Route::post('/vitals', [ClinicalController::class, 'vitalsStore'])->name('vitals.create');
        Route::get('/stays', [ClinicalController::class, 'staysIndex'])->name('stays');
        Route::post('/stays/admit', [ClinicalController::class, 'admit'])->name('stays.admit');
        Route::post('/stays/{stayId}/discharge', [ClinicalController::class, 'discharge'])->name('stays.discharge');
        Route::get('/lab-orders', [ClinicalController::class, 'labIndex'])->name('lab-orders');
        Route::post('/lab-orders/{reportId}/report', [ClinicalController::class, 'labReport'])->name('lab-orders.report');
        Route::patch('/appointments/{appointmentId}/status', [ClinicalController::class, 'appointmentStatus'])->name('appointments.status');

        // OPD queue (PLAN.md 18i): tokens, call-next bell, send-in, duty.
        Route::get('/queue', [QueueController::class, 'index'])->name('queue');
        Route::post('/queue/call-next', [QueueController::class, 'callNext'])->name('queue.call-next');
        Route::post('/appointments/{appointmentId}/send-in', [QueueController::class, 'sendIn'])->name('appointments.send-in');
        Route::patch('/doctors/on-duty', [QueueController::class, 'toggleDuty'])->name('doctors.on-duty');
    });
