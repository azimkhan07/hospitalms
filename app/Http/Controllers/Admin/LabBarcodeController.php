<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvestigationReport;
use Illuminate\Contracts\View\View;

/**
 * Printable sample slip for a collected lab order: the barcode the labeller
 * pastes on the tube plus the patient/test/date it belongs to.
 */
class LabBarcodeController extends Controller
{
    public function show(InvestigationReport $report): View
    {
        abort_unless(hms_can('lab'), 403);

        abort_if(blank($report->barcode), 404);

        return view('admins.prints.lab_barcode', [
            'report' => $report->load(['patient:id,name,age,gender', 'test:id,name,code']),
            'brand' => hms_tenant_brand(),
        ]);
    }
}
