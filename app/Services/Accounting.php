<?php

namespace App\Services;

use App\Models\AccountingVoucher;
use App\Models\bill;
use App\Models\employee;
use App\Models\payment;
use App\Models\SalaryVoucher;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Phase 8 — the accountant's books.
 *
 * Bills carry the money (amount + GST tax, GST-ready invoice no). Payments
 * are the only way money comes in, so every paid payment stamps an income
 * voucher on the ledger. Salaries generate a month's due vouchers for every
 * active staff member with a salary, and paying one books an expense voucher
 * of the same amount. Nothing in the panel writes the ledger directly
 * outside of these two doors.
 */
class Accounting
{
    /**
     * Take a payment against a patient bill. Marks the bill paid, keeps the
     * invoice number, and books the income on the ledger.
     */
    public function receivePayment(payment $payment, ?User $by = null): AccountingVoucher
    {
        $bill = $payment->bill;

        if ($bill) {
            if (! $bill->invoice_no) {
                $bill->invoice_no = 'INV-'.$bill->id;
            }

            // A bill is paid only when the payments fully cover it; partial
            // collections leave the invoice open so the cashier can chase the
            // rest on the same row.
            $paid = (float) $bill->payments()->where('status', 'paid')->sum('amount');

            $bill->status = $paid >= (float) $bill->amount ? 'paid' : 'unpaid';
            $bill->paid_at = $bill->status === 'paid' ? ($bill->paid_at ?? now()) : null;
            $bill->save();
        }

        return AccountingVoucher::create([
            'type' => AccountingVoucher::INCOME,
            'title' => ($bill ? 'Payment '.$bill->invoice_no : 'Patient payment'),
            'note' => ($payment->patient?->name ?? 'Patient').' · '.ucfirst($payment->mode ?? 'cash'),
            'amount' => $payment->amount,
            'ref_type' => 'payment',
            'ref_id' => $payment->id,
            'occurred_at' => now(),
            'created_by' => $by?->id,
        ]);
    }

    /**
     * Generate (or keep) the salary vouchers for one month for every active
     * staff member who has a salary on the employee record.
     *
     * @return int how many vouchers were newly issued
     */
    public function generatePayroll(string $month, ?User $by = null): int
    {
        $month = substr($month, 0, 7);

        $staff = employee::where('status', 'active')
            ->whereNotNull('salary')
            ->where('salary', '>', '0')
            ->get();

        $made = 0;

        foreach ($staff as $person) {
            $voucher = SalaryVoucher::firstOrNew([
                'tenant_id' => $person->tenant_id,
                'employee_id' => $person->id,
                'month' => $month,
            ]);

            if ($voucher->exists) {
                continue;
            }

            $gross = (float) $person->salary;

            $voucher->gross = $gross;
            $voucher->deductions = 0;
            $voucher->net = $gross;
            $voucher->status = 'due';
            $voucher->created_by = $by?->id;
            $voucher->save();

            $made++;
        }

        return $made;
    }

    /**
     * Pay one salary voucher: stamp it paid and book the expense.
     */
    public function paySalary(SalaryVoucher $voucher, ?User $by = null): AccountingVoucher
    {
        if ($voucher->isPaid()) {
            throw new \RuntimeException('This salary is already paid.');
        }

        $voucher->status = 'paid';
        $voucher->paid_at = now();
        $voucher->save();

        $label = Carbon::createFromFormat('Y-m', $voucher->month)->format('M Y');

        return AccountingVoucher::create([
            'type' => AccountingVoucher::EXPENSE,
            'title' => 'Salary · '.($voucher->employee?->name ?? 'Staff').' ('.$label.')',
            'note' => $voucher->note ?: 'Payroll $'.$label,
            'amount' => $voucher->net,
            'ref_type' => 'salary_voucher',
            'ref_id' => $voucher->id,
            'occurred_at' => now(),
            'created_by' => $by?->id,
        ]);
    }

    public static function outstanding(): float
    {
        $open = bill::where('status', 'unpaid')->with('payments')->get();

        return $open->sum(fn (bill $b) => $b->amountDue());
    }

    public static function collectedToday(): float
    {
        return (float) payment::where('status', 'paid')
            ->whereDate('created_at', today())
            ->sum('amount');
    }

    public static function salariesDue(?string $month = null): float
    {
        return (float) SalaryVoucher::where('status', 'due')
            ->where('month', $month ?: now()->format('Y-m'))
            ->sum('net');
    }
}