<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountingVoucher;
use App\Models\bill;
use App\Models\payment;
use App\Models\SalaryVoucher;
use App\Services\Accounting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountingController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $ledger = AccountingVoucher::with('user:id,name')
            ->latest('occurred_at')
            ->limit(20)
            ->get()
            ->map(fn (AccountingVoucher $v) => [
                'id' => $v->id,
                'type' => $v->type,
                'title' => $v->title,
                'note' => $v->note,
                'amount' => (float) $v->amount,
                'occurred_at' => $v->occurred_at?->toIso8601String(),
            ]);

        $openInvoices = bill::with('patient:id,name')
            ->where('status', 'unpaid')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (bill $b) => [
                'id' => $b->id,
                'invoice_no' => $b->invoice_no ?: 'INV-'.$b->id,
                'patient' => $b->patient?->name,
                'amount' => (float) $b->amount,
                'tax' => (float) $b->tax,
                'discount' => (float) $b->discount,
                'paid' => round((float) $b->payments()->where('status', 'paid')->sum('amount'), 2),
                'due' => $b->amountDue(),
            ]);

        $month = request()->string('month', now()->format('Y-m'))->toString();

        return response()->json([
            'success' => true,
            'data' => [
                'outstanding' => round(Accounting::outstanding(), 2),
                'collected_today' => round(Accounting::collectedToday(), 2),
                'salaries_due' => round(Accounting::salariesDue($month), 2),
                'payments_count' => payment::where('status', 'paid')->count(),
                'salary_vouchers' => SalaryVoucher::where('month', $month)->count(),
                'open_invoices' => $openInvoices,
                'ledger' => $ledger,
            ],
        ]);
    }
}