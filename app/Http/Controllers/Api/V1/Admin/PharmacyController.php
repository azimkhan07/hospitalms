<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use App\Services\Pharmacy as PharmacyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pharmacy API mirror of the pharmacist's counter (PLAN.md section 10): the
 * issued prescription queue with per-line quantities and the dispense action
 * that deducts stock for exactly the units the doctor prescribed. Every reply
 * uses the { success, message?, data? } envelope.
 */
class PharmacyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! hms_can('medicines')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $filter = $request->query('filter');

        $page = Prescription::with(['patient:id,name', 'doctor:id,name', 'items'])
            ->where('status', 'issued')
            ->when($filter === 'queue', fn ($q) => $q->whereNull('dispensed_at'))
            ->when($filter === 'dispensed', fn ($q) => $q->whereNotNull('dispensed_at'))
            ->orderByDesc('issued_at')
            ->paginate(8);

        return response()->json([
            'success' => true,
            'data' => [
                'list' => $page->getCollection()->map(fn (Prescription $rx) => [
                    'id' => $rx->id,
                    'patient' => $rx->patient?->name,
                    'doctor' => $rx->doctor?->name,
                    'status' => $rx->status,
                    'issued_at' => $rx->issued_at?->toIso8601String(),
                    'dispensed_at' => $rx->dispensed_at?->toIso8601String(),
                    'payment_status' => $rx->payment_status,
                    'item_count' => $rx->items->count(),
                    'items' => $rx->items->map(fn ($item) => [
                        'id' => $item->id,
                        'medicine' => $item->medicine,
                        'quantity' => $item->quantity,
                        'dispensed_qty' => $item->dispensed_qty,
                    ])->values(),
                ])->values(),
                'pagination' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                ],
            ],
        ]);
    }

    public function dispense(Prescription $prescription): JsonResponse
    {
        if (! hms_can('medicines')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        try {
            app(PharmacyService::class)->dispensePrescription($prescription, auth()->user());
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $prescription->load('items');

        return response()->json([
            'success' => true,
            'message' => 'Dispensed.',
            'data' => [
                'dispensed_items' => $prescription->items->where('dispensed_qty', '>', 0)->count(),
            ],
        ]);
    }
}
