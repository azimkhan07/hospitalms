<?php

/*
|--------------------------------------------------------------------------
| LAB WORKFLOW API
|--------------------------------------------------------------------------
| Sample barcode collection + critical-result escalation.
*/

use App\Http\Controllers\Api\V1\Admin\LabWorkflowController;
use Illuminate\Support\Facades\Route;

Route::post('/lab/{reportId}/barcode', [LabWorkflowController::class, 'barcode'])->name('lab.barcode');
Route::post('/lab/{reportId}/critical', [LabWorkflowController::class, 'critical'])->name('lab.critical');