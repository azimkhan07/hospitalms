<?php

namespace App\Http\Livewire\Admins;

use App\Models\AccountingVoucher;
use App\Models\payment;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The finance day book: every rupee that moved on one date - patient
 * receipts plus ledger vouchers - with the day's totals underneath.
 */
#[Layout('admins.layouts.app')]
class DayBook extends Component
{
    public $date;

    public $showAdvanced = false;

    public function mount(): void
    {
        $this->date = now()->format('Y-m-d');
    }

    public function render()
    {
        if (! hms_can('accounting')) { abort(403); }

        $date = $this->selectedDate();

        return view('livewire.admins.day-book', [
            'entries' => static::entriesFor($date),
            'totals' => static::totalsFor($date),
        ]);
    }

    /** The picked day as Y-m-d, falling back to today on junk input. */
    protected function selectedDate(): string
    {
        try {
            return Carbon::parse($this->date)->format('Y-m-d');
        } catch (\Throwable) {
            return now()->format('Y-m-d');
        }
    }

    /**
     * Receipts and ledger vouchers of one day, newest first. Shared with the
     * billing API so the mobile app reads the exact same day book.
     */
    public static function entriesFor(string $date): Collection
    {
        $receipts = payment::with(['bill:id,invoice_no', 'patient:id,name'])
            ->where('status', 'paid')
            ->whereDate('created_at', $date)
            ->latest('created_at')
            ->get()
            ->map(fn (payment $p): array => [
                'source' => 'receipt',
                'time' => $p->created_at?->format('H:i'),
                'title' => ($p->bill?->invoice_no ?: 'INV-'.$p->bill_id).' - '.($p->patient?->name ?? 'Patient'),
                'note' => ucfirst($p->mode ?? 'cash').' receipt',
                'ref' => 'payment#'.$p->id,
                'type' => 'in',
                'amount' => (float) $p->amount,
                'occurred_at' => $p->created_at,
            ]);

        $vouchers = AccountingVoucher::whereDate('occurred_at', $date)
            ->latest('occurred_at')
            ->get()
            ->map(fn (AccountingVoucher $v): array => [
                'source' => 'voucher',
                'time' => $v->occurred_at?->format('H:i'),
                'title' => $v->title,
                'note' => $v->note,
                'ref' => $v->ref_type ? $v->ref_type.'#'.$v->ref_id : null,
                'type' => $v->isIncome() ? 'in' : 'out',
                'amount' => (float) $v->amount,
                'occurred_at' => $v->occurred_at,
            ]);

        return $receipts->concat($vouchers)->sortByDesc('occurred_at')->values();
    }

    public static function totalsFor(string $date): array
    {
        $receipts = (float) payment::where('status', 'paid')
            ->whereDate('created_at', $date)
            ->sum('amount');

        $voucherInflow = (float) AccountingVoucher::where('type', AccountingVoucher::INCOME)
            ->whereDate('occurred_at', $date)
            ->sum('amount');

        $voucherOutflow = (float) AccountingVoucher::where('type', AccountingVoucher::EXPENSE)
            ->whereDate('occurred_at', $date)
            ->sum('amount');

        return [
            'receipts' => round($receipts, 2),
            'voucher_inflow' => round($voucherInflow, 2),
            'voucher_outflow' => round($voucherOutflow, 2),
            'net' => round($receipts + $voucherInflow - $voucherOutflow, 2),
        ];
    }
}
