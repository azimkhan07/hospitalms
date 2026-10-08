<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PrintListingRequest;
use App\Models\bill;
use App\Models\patient;
use App\Models\Prescription;
use Illuminate\Http\JsonResponse;

/**
 * API mirror of the Print Center (PLAN.md 18d): the mobile app browses the
 * same document register the web panel lists. Actual paper downloads stay on
 * the web panel where the receipt printers live.
 */
class PrintController extends Controller
{
    public function index(PrintListingRequest $request): JsonResponse
    {
        $type = $request->type();
        $search = $request->search();

        $rows = match ($type) {
            'medicine-slip' => patient::query()
                ->with(['stays' => fn ($q) => $q->orderBy('start_time', 'desc')])
                ->when($search, fn ($q, $s) => $q->where(function ($w) use ($s) {
                    $w->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%");
                }))
                ->latest()
                ->paginate((int) ($request->validated('per_page') ?: 15))
                ->withQueryString()
                ->toArray(),

            'case-paper' => patient::query()
                ->with([
                    'prescriptions.items',
                    'stays' => fn ($q) => $q->with('room:id,name', 'bed:id,bed_number')->latest('start_time'),
                ])
                ->when($search, fn ($q, $s) => $q->where(function ($w) use ($s) {
                    $w->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%");
                }))
                ->latest()
                ->paginate((int) ($request->validated('per_page') ?: 15))
                ->withQueryString()
                ->toArray(),

            'prescription' => Prescription::query()
                ->with(['patient:id,name,phone', 'doctor:id,name', 'items'])
                ->when($search, fn ($q, $s) => $q->whereHas('patient', fn ($p) => $p
                    ->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")))
                ->latest('issued_at')
                ->paginate((int) ($request->validated('per_page') ?: 15))
                ->withQueryString()
                ->toArray(),

            default => bill::query()
                ->with(['patient:id,name,phone,address,age,gender', 'payments', 'issuer:id,name'])
                ->when($search, fn ($q, $s) => $q->whereHas('patient', fn ($p) => $p
                    ->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")))
                ->latest()
                ->paginate((int) ($request->validated('per_page') ?: 15))
                ->withQueryString()
                ->toArray(),
        };

        return response()->json([
            'success' => true,
            'data' => [
                'type' => $type,
                'documents' => $rows,
            ],
        ]);
    }
}