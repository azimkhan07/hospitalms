<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API mirror of the Diagnostics → Machines master (PLAN.md 9d.1).
 */
class MachinesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! hms_can('machines')) {
            abort(403);
        }

        $machines = Machine::with(['room:id,name', 'bed:id,bed_number'])
            ->when($request->string('status')->toString() !== '', fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->string('modality')->toString() !== '', fn ($q) => $q->where('modality', $request->string('modality')->toString()))
            ->orderBy('name')
            ->limit(100)
            ->get()
            ->map(fn (Machine $m): array => [
                'id' => $m->id,
                'name' => $m->name,
                'code' => $m->code,
                'modality' => $m->modality,
                'department' => $m->department,
                'location' => $m->location_label,
                'room' => $m->room?->name,
                'bed' => $m->bed?->bed_number,
                'serial_number' => $m->serial_number,
                'vendor' => $m->vendor,
                'purchase_date' => $m->purchase_date?->toDateString(),
                'warranty_ends_at' => $m->warranty_ends_at?->toDateString(),
                'warranty_expired' => $m->warranty_expired,
                'status' => $m->status,
                'rate' => (float) $m->rate,
            ]);

        return response()->json(['success' => true, 'data' => $machines]);
    }
}