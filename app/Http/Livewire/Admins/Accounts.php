<?php

namespace App\Http\Livewire\Admins;

use App\Models\AccountingVoucher;
use App\Models\bill;
use App\Models\payment;
use App\Models\SalaryVoucher;
use App\Services\Accounting;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('admins.layouts.app')]
class Accounts extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public string $tab = 'overview';

    public $month;

    public $receiveBillId;

    public $receiveAmount;

    public $receiveMode = 'cash';

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function accept(string $billId): void
    {
        $this->receiveBillId = (int) $billId;
        $this->receiveAmount = null;
        $this->receiveMode = 'cash';
        $this->tab = 'overview';
    }

    public function recordPayment(): void
    {
        $this->validate([
            'receiveAmount' => 'required|numeric|gt:0',
            'receiveMode' => 'required|in:cash,card,cheque,online',
        ]);

        $bill = bill::findOrFail($this->receiveBillId);

        $payment = payment::create([
            'patient_id' => $bill->patients_id,
            'bill_id' => $bill->id,
            'amount' => number_format($this->receiveAmount, 2, '.', ''),
            'status' => 'paid',
            'mode' => $this->receiveMode,
        ]);

        app(Accounting::class)->receivePayment($payment);

        session()->flash('message', 'Payment of '.number_format((float) $this->receiveAmount, 2).' recorded for INV-'.$bill->id.'.');

        $this->receiveBillId = null;
        $this->receiveAmount = null;
    }

    public function generatePayroll(): void
    {
        $made = app(Accounting::class)->generatePayroll($this->month, auth()->user());

        if ($made > 0) {
            session()->flash('message', 'Payroll for '.$this->month.': '.$made.' salary voucher(s) issued.');
        } else {
            session()->flash('message', 'No new salary vouchers for '.$this->month.' (already issued).');
        }
    }

    public function paySalary(int $voucherId): void
    {
        $voucher = SalaryVoucher::findOrFail($voucherId);

        app(Accounting::class)->paySalary($voucher, auth()->user());

        session()->flash('message', 'Salary paid - expense of '.number_format((float) $voucher->net, 2).' posted to the ledger.');
    }

    public function render()
    {
        abort_unless(hms_can('accounting'), 403);

        if ($this->tab === 'overview') {
            $openBills = bill::with('patient')->where('status', 'unpaid')->latest()->paginate(8);
        } else {
            $openBills = bill::with('patient')->where('status', 'unpaid')->latest()->limit(8)->get();
        }

        // Only the active tab paginates. Livewire supports one paginator per
        // component, so the other two lists render unbounded.
        if ($this->tab === 'ledger') {
            $vouchers = AccountingVoucher::with('user')->latest('occurred_at')->paginate(12);
        } else {
            $vouchers = AccountingVoucher::latest('occurred_at')->limit(6)->get();
        }

        if ($this->tab === 'payroll') {
            $salaries = SalaryVoucher::with('employee')
                ->where('month', $this->month)
                ->orderByDesc('status')
                ->orderByDesc('id')
                ->paginate(12);
        } else {
            $salaries = SalaryVoucher::with('employee')
                ->where('month', $this->month)
                ->latest()
                ->get();
        }

        return view('livewire.admins.accounts', [
            'openBills' => $openBills,
            'vouchers' => $vouchers,
            'salaries' => $salaries,
            'outstanding' => Accounting::outstanding(),
            'collectedToday' => Accounting::collectedToday(),
            'salariesDue' => Accounting::salariesDue($this->month),
        ]);
    }
}
