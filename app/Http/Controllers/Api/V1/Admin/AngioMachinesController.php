<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AngioMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AngioMachinesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! hms_can('angio')) {
            abort(403);
        }

        $machines = AngioMachine::withCount('appointments')
            ->when($request->string('status')->toString() !== '', fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->orderBy('name')
            ->limit(100)
            ->get()
            ->map(fn (AngioMachine $m): array => [
                'id' => $m->id,
                'name' => $m->name,
                'code' => $m->code,
                'manufacturer' => $m->manufacturer,
                'model' => $m->model,
                'serial_number' => $m->serial_number,
                'location' => $m->location,
                'installed_at' => $m->installed_at?->toDateString(),
                'status' => $m->status,
                'rate' => (float) $m->rate,
                'treatments_count' => $m->appointments_count,
            ]);

        return response()->json(['success' => true, 'data' => $machines]);
    }
}