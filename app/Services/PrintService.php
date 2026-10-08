<?php

namespace App\Services;

use App\Models\bill;
use App\Models\patient;
use App\Models\Prescription;
use App\Models\stay;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;

/**
 * Generates the print papers the hospital hands out, in the three physical
 * sizes the hardware needs:
 *
 *  1. A4              -- final invoice / bill
 *  2. 58 mm thermal   -- the small hand-top slip for a day's medicines while
 *                        the patient is admitted
 *  3. Medium (A5)     -- clinic case paper and the prescription
 */
class PrintService
{
    /** Height (pt) for the 58 mm roll; tall enough not to clip a long slip. */
    private const THERMAL_HEIGHT = 2000;

    public function invoiceDownload(bill $bill): \Barryvdh\DomPDF\PDF
    {
        $bill->load(['patient:id,name,phone,address,age,gender', 'payments', 'issuer:id,name']);

        return Pdf::setPaper('a4', 'portrait')
            ->loadView('prints.invoice', ['bill' => $bill]);
    }

    /**
     * Small hand-top slip: every medicine handed to this admitted patient on
     * the given calendar day.
     */
    public function medicineSlip(patient $patient, ?string $date = null): \Barryvdh\DomPDF\PDF
    {
        $day = $date ? Carbon::parse($date) : today();

        $lines = \Illuminate\Support\Facades\DB::table('prescription_items as pi')
            ->join('prescriptions as p', 'p.id', '=', 'pi.prescription_id')
            ->leftJoin('medicines as m', 'm.id', '=', 'pi.medicine_id')
            ->where('p.patient_id', $patient->id)
            ->whereDate('pi.dispensed_at', $day)
            ->select('pi.medicine', 'm.name as drug_name', 'pi.dosage', 'pi.frequency', 'pi.duration', 'pi.dispensed_qty')
            ->orderBy('pi.id')
            ->get();

        return Pdf::setPaper([0, 0, 164.4, self::THERMAL_HEIGHT], 'portrait')
            ->loadView('prints.medicine-slip', [
                'patient' => $patient->load([
                    'stays' => fn ($q) => $q->with('room:id,name', 'bed:id,bed_number')->latest('start_time'),
                ]),
                'day' => $day,
                'lines' => $lines,
            ]);
    }

    /**
     * Clinic case paper (A5). The full record: demographics, admissions,
     * prescriptions and checkups, kept tidy enough to sit on a clipboard.
     */
    public function casePaper(patient $patient): \Barryvdh\DomPDF\PDF
    {
        $patient->load([
            'prescriptions' => fn ($q) => $q->with('items:id,prescription_id,medicine,dosage,frequency,duration')->latest('issued_at'),
            'stays' => fn ($q) => $q->with('room:id,name', 'bed:id,bed_number')->latest('start_time'),
        ]);

        return Pdf::setPaper('a5', 'portrait')
            ->loadView('prints.case-paper', ['patient' => $patient]);
    }

    public function prescription(Prescription $prescription): \Barryvdh\DomPDF\PDF
    {
        $prescription->load(['patient:id,name,phone,age,gender', 'doctor:id,name', 'items:id,prescription_id,medicine,dosage,frequency,duration,note']);

        return Pdf::setPaper('a5', 'portrait')
            ->loadView('prints.prescription', ['prescription' => $prescription]);
    }
}