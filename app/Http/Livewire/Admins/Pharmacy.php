<?php

namespace App\Http\Livewire\Admins;

use App\Models\bill;
use App\Models\payment;
use App\Models\Prescription;
use App\Services\Pharmacy as PharmacyService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Phase 7 — the pharmacist's counter (PLAN.md section 10).
 *
 * The doctor's issued prescriptions land here. Each line shows what is in the
 * master, which batch is actually in stock (earliest expiry first) and how much
 * is left. "Dispense" moves the stock in the ledger and stamps the line; once
 * every line has moved, "Payment done" posts the handover to the patient's bill
 * through the same tables the accountant uses.
 */
#[Layout('admins.layouts.app')]
class Pharmacy extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public string $search = '';

    public ?int $openId = null;

    public string $filter = 'queue'; // queue | dispensed | all

    public function render()
    {
        if (! hms_can('medicines')) {
            abort(403);
        }

        $prescriptions = Prescription::with(['patient:id,name', 'items.drug', 'doctor:id,name'])
            ->where('status', 'issued')
            ->when($this->filter === 'queue', fn ($q) => $q->whereNull('dispensed_at'))
            ->when($this->filter === 'dispensed', fn ($q) => $q->whereNotNull('dispensed_at'))
            ->when($this->search !== '', function ($q) {
                $term = '%'.mb_strtolower($this->search).'%';
                $q->whereHas('patient', fn ($p) => $p->whereRaw('LOWER(name) LIKE ?', [$term]));
            })
            ->orderByDesc('issued_at')
            ->paginate(8);

        $open = $this->openId ? Prescription::with(['patient', 'items.drug', 'doctor'])->find($this->openId) : null;

        return view('livewire.admins.pharmacy', [
            'prescriptions' => $prescriptions,
            'open' => $open,
            'pharmacy' => app(PharmacyService::class),
        ]);
    }

    public function open(int $id): void
    {
        $this->openId = $id;
    }

    public function close(): void
    {
        $this->openId = null;
    }

    public function dispense(): void
    {
        $open = $this->openPrescription();

        try {
            app(PharmacyService::class)->dispensePrescription($open, auth()->user());
            session()->flash('success', 'Dispensed. Stock deducted from the earliest-expiry batch.');
        } catch (\RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->openId = null;
    }

    /**
     * Mark the prescription paid: create (or reuse) the patient's unpaid bill
     * and post one payment for the dispensed total. Posting reuses the bill and
     * payment rows the accountant works with.
     */
    public function markPaid(string $mode = 'cash'): void
    {
        $open = $this->openPrescription();

        if (! $open->isFullyDispensed()) {
            session()->flash('error', 'Dispense every line before taking payment.');

            return;
        }

        $total = 0.0;

        foreach ($open->items as $item) {
            $unit = $item->drug ? (float) ($item->drug->mrp ?? $item->drug->price) : 0.0;

            $total += $unit * $item->dispensed_qty;
        }

        $bill = bill::firstOrCreate(
            ['patients_id' => $open->patient_id, 'status' => 'unpaid'],
            ['patients_id' => $open->patient_id, 'status' => 'unpaid', 'issued_by' => auth()->id()]
        );

        $bill->amount = (float) $bill->amount + $total;
        $bill->invoice_no ??= 'INV-'.$bill->id;
        $bill->issued_by ??= auth()->id();
        $bill->save();

        $payment = payment::create([
            'patient_id' => $open->patient_id,
            'bill_id' => $bill->id,
            'amount' => number_format($total, 2, '.', ''),
            'status' => 'paid',
            'mode' => $mode,
        ]);

        app(\App\Services\Accounting::class)->receivePayment($payment);

        $open->update(['payment_status' => 'paid']);

        session()->flash('success', 'Payment of '.number_format($total, 2).' posted to the patient bill.');

        $this->openId = null;
    }

    private function openPrescription(): Prescription
    {
        abort_unless($this->openId, 422);

        return Prescription::with('items.drug')->findOrFail($this->openId);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }
}