<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PrintListingRequest;
use App\Models\bill;
use App\Models\patient;
use App\Models\Prescription;
use App\Services\PrintService;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Print Center: one listing action (filter + search + eager loaded pagination
 * through PrintListingRequest) plus the per-document PDF downloads.
 */
class PrintController extends Controller
{
    public function __construct(protected PrintService $printer)
    {
    }

    /**
     * Single function the whole centre is built on: reads the validated
     * request (type / search / date), eager loads exactly what that document
     * needs and returns the paginated row set.
     */
    public function index(PrintListingRequest $request): View
    {
        $type = $request->type();
        $search = $request->search();
        $perPage = (int) ($request->validated('per_page') ?: 15);

        $rows = match ($type) {
            'medicine-slip' => patient::query()
                ->with(['stays' => fn ($q) => $q->orderBy('start_time', 'desc')])
                ->when($search, fn ($q, $s) => $q->where(function ($w) use ($s) {
                    $w->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%");
                }))
                ->latest()
                ->paginate($perPage),

            'case-paper' => patient::query()
                ->with([
                    'prescriptions.items',
                    'stays' => fn ($q) => $q->with('room:id,name', 'bed:id,bed_number')->latest('start_time'),
                ])
                ->when($search, fn ($q, $s) => $q->where(function ($w) use ($s) {
                    $w->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%");
                }))
                ->latest()
                ->paginate($perPage),

            'prescription' => Prescription::query()
                ->with(['patient:id,name,phone', 'doctor:id,name', 'items'])
                ->when($search, fn ($q, $s) => $q->whereHas('patient', fn ($p) => $p
                    ->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")))
                ->latest('issued_at')
                ->paginate($perPage),

            default => bill::query()
                ->with(['patient:id,name,phone,address,age,gender', 'payments', 'issuer:id,name'])
                ->when($search, fn ($q, $s) => $q->whereHas('patient', fn ($p) => $p
                    ->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")))
                ->latest()
                ->paginate($perPage),
        };

        return view('prints.center', [
            'rows' => $rows,
            'type' => $type,
            'search' => $search,
            'date' => $request->slipDate(),
            'request' => $request,
        ]);
    }

    public function invoice(bill $bill, PrintListingRequest $request): Response
    {
        abort_unless(hms_can('printout'), 403);

        return $this->printer->invoiceDownload($bill)
            ->download("invoice-{$bill->id}.pdf");
    }

    public function medicineSlip(patient $patient, PrintListingRequest $request): Response
    {
        abort_unless(hms_can('printout'), 403);

        return $this->printer->medicineSlip($patient, $request->query('date'))
            ->download("medicine-slip-{$patient->id}.pdf");
    }

    public function casePaper(patient $patient, PrintListingRequest $request): Response
    {
        abort_unless(hms_can('printout'), 403);

        return $this->printer->casePaper($patient)
            ->download("case-paper-{$patient->id}.pdf");
    }

    public function prescription(Prescription $prescription, PrintListingRequest $request): Response
    {
        abort_unless(hms_can('printout'), 403);

        return $this->printer->prescription($prescription)
            ->download("prescription-{$prescription->id}.pdf");
    }
}