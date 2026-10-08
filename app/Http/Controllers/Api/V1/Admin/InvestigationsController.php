<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvestigationTest;
use App\Services\InvestigationCharge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API mirror of the Diagnostics → Rate Card (PLAN.md 9d.2): every test the
 * facility sells, plus the formula one unit would be billed for, so the mobile
 * app and the counter show the same number.
 */
class InvestigationsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! hms_can('investigations')) {
            abort(403);
        }

        $tests = InvestigationTest::with('machine')
            ->when($request->boolean('active') || $request->string('active')->toString() === '', fn ($q) => $q->active())
            ->when(
                $request->has('active') && $request->string('active')->toString() !== '' && ! $request->boolean('active'),
                fn ($q) => $q->where('is_active', false)
            )
            ->when($request->string('department')->toString() !== '', fn ($q) => $q->where('department', $request->string('department')->toString()))
            ->orderBy('name')
            ->limit(200)
            ->get()
            ->map(function (InvestigationTest $t): array {
                $charge = InvestigationCharge::for($t);

                return [
                    'id' => $t->id,
                    'name' => $t->name,
                    'code' => $t->code,
                    'department' => $t->department,
                    'machine' => $t->machine?->name,
                    'machine_id' => $t->machine_id,
                    'calc_type' => $t->calc_type,
                    'calc_type_label' => InvestigationTest::CALC_TYPES[$t->calc_type] ?? $t->calc_type,
                    'base_rate' => (float) $t->base_rate,
                    'per_unit_rate' => (float) $t->per_unit_rate,
                    'urgent_factor' => (float) $t->urgent_factor,
                    'max_units' => $t->max_units,
                    'turnaround_hours' => $t->turnaround_hours,
                    'sample_type' => $t->sample_type,
                    'is_active' => $t->is_active,
                    'charge_for_one' => $charge->total(),
                    'formula' => $charge->formula(),
                ];
            });

        return response()->json(['success' => true, 'data' => $tests]);
    }
}