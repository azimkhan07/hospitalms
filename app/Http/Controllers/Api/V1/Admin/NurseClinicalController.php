<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\DoctorAlert;
use App\Models\DrugChart;
use App\Models\DrugChartAdministration;
use App\Models\Handover;
use App\Models\stay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API mirror of the nurse's clinical screens (PLAN.md Phase 6): the drug
 * chart and its administration log, the shift handover notes and the doctor
 * alerts raised from the ward. Every route re-checks the same 'ward' module
 * gate the web screens use.
 */
class NurseClinicalController extends Controller
{
    public function drugChartsIndex(Request $request): JsonResponse
    {
        if (! hms_can('ward')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $charts = DrugChart::with(['patient:id,name', 'stay.room:id,name', 'stay.bed:id,bed_number,room_id', 'administrations:id,drug_chart_id,state,given_at'])
            ->when($request->integer('stay_id') > 0, fn ($q) => $q->where('stay_id', $request->integer('stay_id')))
            ->orderByDesc('id')
            ->get()
            ->map(fn (DrugChart $chart) => [
                'id' => $chart->id,
                'stay_id' => $chart->stay_id,
                'patient' => $chart->patient?->name,
                'bed' => $chart->stay?->bedLabel(),
                'medicine' => $chart->medicine,
                'dosage' => $chart->dosage,
                'frequency' => $chart->frequency,
                'route' => $chart->route,
                'duration_days' => $chart->duration_days,
                'start_date' => optional($chart->start_date)->format('Y-m-d'),
                'notes' => $chart->notes,
                'status' => $chart->status,
                'administrations_count' => $chart->administrations->count(),
                'administrations_states' => $chart->administrations->pluck('state')->all(),
            ]);

        return response()->json(['success' => true, 'data' => $charts]);
    }

    public function drugChartsStore(Request $request): JsonResponse
    {
        if (! hms_can('ward')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'medicine' => ['required', 'string', 'max:200'],
            'stay_id' => ['required', 'integer', 'exists:stays,id'],
            'dosage' => ['nullable', 'string', 'max:100'],
            'frequency' => ['nullable', 'string', 'max:100'],
            'route' => ['nullable', 'string', 'max:100'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'start_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // The stay decides which patient the line belongs to.
        $stay = stay::findOrFail($data['stay_id']);

        $chart = DrugChart::create([
            'stay_id' => $stay->id,
            'patient_id' => $stay->patient_id,
            'medicine' => $data['medicine'],
            'dosage' => $data['dosage'] ?? null,
            'frequency' => $data['frequency'] ?? null,
            'route' => $data['route'] ?? null,
            'duration_days' => $data['duration_days'] ?? null,
            'start_date' => $data['start_date'] ?? today()->toDateString(),
            'notes' => $data['notes'] ?? null,
            'ordered_by' => $request->user()->id,
            'status' => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Drug chart line added.',
            'data' => $chart,
        ]);
    }

    public function administrations(Request $request, int $chartId): JsonResponse
    {
        if (! hms_can('ward')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $chart = DrugChart::findOrFail($chartId);

        return response()->json([
            'success' => true,
            'data' => $chart->administrations()->with('giver:id,name')->orderByDesc('id')->get(),
        ]);
    }

    public function administer(Request $request, int $chartId): JsonResponse
    {
        if (! hms_can('ward')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'state' => ['required', 'in:'.implode(',', DrugChartAdministration::STATES)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $chart = DrugChart::findOrFail($chartId);

        $record = DrugChartAdministration::create([
            'drug_chart_id' => $chart->id,
            'scheduled_time' => now(),
            'given_at' => now(),
            'given_by' => $request->user()->id,
            'state' => $data['state'],
            'note' => $data['note'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Administration recorded: '.$data['state'].'.',
            'data' => $record,
        ]);
    }

    public function handoversIndex(Request $request): JsonResponse
    {
        if (! hms_can('ward')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => Handover::with('originator:id,name')->latest()->limit(30)->get(),
        ]);
    }

    public function handoversStore(Request $request): JsonResponse
    {
        if (! hms_can('ward')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'notes' => ['required', 'string', 'max:4000'],
            'ward' => ['nullable', 'string', 'max:100'],
            'to_role' => ['nullable', 'string', 'max:50'],
        ]);

        $handover = Handover::create([
            'from_user' => $request->user()->id,
            'ward' => $data['ward'] ?? null,
            'to_role' => $data['to_role'] ?? null,
            'notes' => $data['notes'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Handover recorded.',
            'data' => $handover,
        ]);
    }

    public function alertsIndex(Request $request): JsonResponse
    {
        if (! hms_can('ward')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $alerts = DoctorAlert::with(['patient:id,name', 'raiser:id,name'])
            ->orderByRaw('CASE WHEN resolved_at IS NULL THEN 0 ELSE 1 END')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (DoctorAlert $alert) => [
                'id' => $alert->id,
                'patient' => $alert->patient?->name,
                'stay_id' => $alert->stay_id,
                'category' => $alert->category,
                'message' => $alert->message,
                'is_urgent' => (bool) $alert->is_urgent,
                'raised_by' => $alert->raiser?->name,
                'created_at' => optional($alert->created_at)->toDateTimeString(),
                'resolved_at' => optional($alert->resolved_at)->toDateTimeString(),
            ]);

        return response()->json(['success' => true, 'data' => $alerts]);
    }

    public function alertsStore(Request $request): JsonResponse
    {
        if (! hms_can('ward')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'category' => ['required', 'in:'.implode(',', DoctorAlert::CATEGORIES)],
            'message' => ['required', 'string', 'max:2000'],
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'stay_id' => ['nullable', 'integer', 'exists:stays,id'],
            'is_urgent' => ['nullable', 'boolean'],
        ]);

        $alert = DoctorAlert::create([
            'patient_id' => $data['patient_id'] ?? null,
            'stay_id' => $data['stay_id'] ?? null,
            'raised_by' => $request->user()->id,
            'category' => $data['category'],
            'message' => $data['message'],
            'is_urgent' => ! empty($data['is_urgent']),
        ]);

        $alert->notifyDoctors();

        return response()->json([
            'success' => true,
            'message' => 'Doctor alerted.',
            'data' => $alert,
        ]);
    }

    public function alertAck(Request $request, int $alertId): JsonResponse
    {
        if (! hms_can('ward')) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $alert = DoctorAlert::findOrFail($alertId);

        $alert->update([
            'resolved_at' => now(),
            'resolved_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Alert acknowledged.',
            'data' => $alert->fresh(),
        ]);
    }
}
