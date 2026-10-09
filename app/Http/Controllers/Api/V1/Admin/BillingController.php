<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Livewire\Admins\DayBook;
use App\Models\BillItem;
use App\Models\bill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Billing API (PLAN billing milestone): line items, totals, the IPD
 * discharge finalisation and the finance day book, all in the standard
 * { success, message?, data? } envelope.
 */
class BillingController extends Controller
{
    public function items(bill $bill): JsonResponse
    {
        if (! hms_can('bills')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $bill->items()->orderBy('id')->get(),
                'totals' => $this->totals($bill),
            ],
        ]);
    }

    public function storeItem(Request $request, bill $bill): JsonResponse
    {
        if (! hms_can('bills')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'description' => 'required|string|max:255',
            'category' => 'nullable|in:'.implode(',', BillItem::CATEGORIES),
            'qty' => 'required|numeric|min:0.01',
            'rate' => 'required|numeric|min:0',
        ]);

        $item = BillItem::create([
            'bill_id' => $bill->id,
            'description' => $data['description'],
            'category' => $data['category'] ?? 'other',
            'qty' => $data['qty'],
            'rate' => $data['rate'],
            'amount' => round((float) $data['qty'] * (float) $data['rate'], 2),
            'created_by' => $request->user()?->id,
        ]);

        $bill->recalculate();

        return response()->json([
            'success' => true,
            'message' => 'Line item added.',
            'data' => [
                'item' => $item,
                'totals' => $this->totals($bill),
            ],
        ]);
    }

    public function destroyItem(bill $bill, BillItem $item): JsonResponse
    {
        if (! hms_can('bills')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        if ((int) $item->bill_id !== (int) $bill->id) {
            return response()->json(['success' => false, 'message' => 'Line item does not belong to this bill.'], 404);
        }

        $item->delete();
        $bill->recalculate();

        return response()->json([
            'success' => true,
            'message' => 'Line item removed.',
            'data' => ['totals' => $this->totals($bill)],
        ]);
    }

    public function finaliseStay(bill $bill): JsonResponse
    {
        if (! hms_can('bills')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        if (! $bill->finaliseFromStay()) {
            return response()->json([
                'success' => false,
                'message' => 'No IPD stay found for this bill.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Final bill generated from the IPD stay.',
            'data' => [
                'bill' => $bill->fresh(),
                'items' => $bill->items()->orderBy('id')->get(),
                'totals' => $this->totals($bill),
            ],
        ]);
    }

    public function update(Request $request, bill $bill): JsonResponse
    {
        if (! hms_can('bills')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'discount_amount' => 'nullable|numeric|min:0',
            'advance_used' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:255',
        ]);

        if (array_key_exists('discount_amount', $data)) {
            $bill->discount_amount = (float) ($data['discount_amount'] ?? 0);
        }

        if (array_key_exists('advance_used', $data)) {
            $bill->advance_used = (float) ($data['advance_used'] ?? 0);
        }

        if (array_key_exists('remarks', $data)) {
            $bill->remarks = $data['remarks'];
        }

        $bill->recalculate();

        return response()->json([
            'success' => true,
            'message' => 'Bill updated.',
            'data' => [
                'bill' => $bill->fresh(),
                'totals' => $this->totals($bill),
            ],
        ]);
    }

    public function dayBook(Request $request): JsonResponse
    {
        if (! hms_can('accounting')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $data = $request->validate(['date' => 'nullable|date']);

        $date = ! empty($data['date'])
            ? Carbon::parse($data['date'])->format('Y-m-d')
            : now()->format('Y-m-d');

        return response()->json([
            'success' => true,
            'data' => array_merge(
                ['date' => $date, 'entries' => DayBook::entriesFor($date)],
                DayBook::totalsFor($date)
            ),
        ]);
    }

    private function totals(bill $bill): array
    {
        return [
            'gross' => round($bill->grossAmount(), 2),
            'tax' => round((float) $bill->tax, 2),
            'discount' => round((float) $bill->discount, 2),
            'discount_amount' => round((float) $bill->discount_amount, 2),
            'advance_used' => round((float) $bill->advance_used, 2),
            'net' => round($bill->netAmount(), 2),
            'paid' => round($bill->paidTotal(), 2),
            'due' => round($bill->amountDue(), 2),
        ];
    }
}
