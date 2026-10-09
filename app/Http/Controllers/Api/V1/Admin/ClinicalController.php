<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\appointment;
use App\Models\beds;
use App\Models\InvestigationReport;
use App\Models\patient;
use App\Models\stay;
use App\Models\Vital;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API mirror of the clinical screens (PLAN.md section 6/7): vitals capture,
 * the IPD ward (admit / discharge) and the lab queue. Every route re-checks
 * the same module gates the web screens use.
 */
class ClinicalController extends Controller
{
    public function vitalsIndex(Request $request): JsonResponse
    {
        abort_unless(hms_can('vitals'), 403);

        $query = Vital::with(['patient:id,name', 'recorder:id,name', 'appointment:id,status'])
            ->when($request->integer('patient_id') > 0, fn ($q) => $q->where('patient_id', $request->integer('patient_id')))
            ->when($request->integer('appointment_id') > 0, fn ($q) => $q->where('appointment_id', $request->integer('appointment_id')))
            ->when($request->integer('stay_id') > 0, fn ($q) => $q->where('stay_id', $request->integer('stay_id')))
            ->latest('taken_at');

        return $this->ok($query->limit(min($request->integer('per_page', 25), 100))->get());
    }

    public function vitalsStore(Request $request): JsonResponse
    {
        abort_unless(hms_can('vitals'), 403);

        $data = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'stay_id' => ['nullable', 'integer', 'exists:stays,id'],
            'bp_systolic' => ['nullable', 'integer', 'between:30,300'],
            'bp_diastolic' => ['nullable', 'integer', 'between:20,200'],
            'pulse' => ['nullable', 'integer', 'between:20,250'],
            'temperature' => ['nullable', 'numeric', 'between:25,45'],
            'spo2' => ['nullable', 'integer', 'between:40,100'],
            'weight' => ['nullable', 'numeric', 'between:0.5,400'],
            'height' => ['nullable', 'numeric', 'between:30,250'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $readings = array_filter([
            $data['bp_systolic'] ?? null, $data['pulse'] ?? null,
            $data['temperature'] ?? null, $data['spo2'] ?? null,
            $data['weight'] ?? null, $data['height'] ?? null,
        ], fn ($v) => $v !== null);

        if (! $readings) {
            return response()->json([
                'success' => false,
                'message' => 'Record at least one measurement.',
            ], 422);
        }

        // Never file an observation under an encounter of another patient.
        if (! empty($data['appointment_id']) && (int) appointment::find($data['appointment_id'])?->patient_id !== (int) $data['patient_id']) {
            unset($data['appointment_id']);
        }

        if (! empty($data['stay_id']) && (int) stay::find($data['stay_id'])?->patient_id !== (int) $data['patient_id']) {
            unset($data['stay_id']);
        }

        $vital = Vital::create($data + [
            'taken_by' => $request->user()->id,
            'taken_at' => now(),
        ]);

        if (! empty($data['appointment_id'])) {
            $appt = appointment::find($data['appointment_id']);

            if ($appt && in_array($appt->status, ['pending', 'confirmed'], true)) {
                $appt->update(['status' => 'waiting']);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Vitals recorded.',
            'data' => $vital->fresh(),
        ], 201);
    }

    public function staysIndex(Request $request): JsonResponse
    {
        abort_unless(hms_can('ward'), 403);

        $status = $request->string('status')->toString() ?: 'active';

        $stays = stay::with(['patient:id,name,age,gender,bloodgroup', 'room:id,name', 'bed:id,bed_number,room_id', 'latestVital'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->string('search')->toString() !== '', function ($q) use ($request) {
                $term = '%'.mb_strtolower($request->string('search')->toString()).'%';
                $q->whereHas('patient', fn ($p) => $p->whereRaw('LOWER(name) LIKE ?', [$term]));
            })
            ->orderByDesc('start_time')
            ->limit(min($request->integer('per_page', 25), 100))
            ->get();

        return $this->ok($stays);
    }

    public function admit(Request $request): JsonResponse
    {
        abort_unless(hms_can('beds.allocate'), 403);

        $data = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'bed_id' => ['required', 'integer', 'exists:beds,id'],
        ]);

        $bed = beds::findOrFail($data['bed_id']);

        if (! $bed->isAllocatable()) {
            return response()->json([
                'success' => false,
                'message' => 'That bed is not free.',
            ], 422);
        }

        $stay = DB::transaction(function () use ($data, $bed) {
            $stay = stay::create([
                'patient_id' => $data['patient_id'],
                'room_id' => $bed->room_id,
                'bed_id' => $bed->id,
                'start_time' => now()->timestamp,
                'status' => 'active',
                'amount' => 0,
                'discount' => 0,
                'total' => 0,
            ]);

            $bed->update([
                'patient_id' => $data['patient_id'],
                'status' => 'alloted',
                'alloted_time' => now(),
                'discharge_time' => null,
            ]);

            return $stay;
        });

        return response()->json([
            'success' => true,
            'message' => 'Patient admitted.',
            'data' => $stay->fresh(),
        ], 201);
    }

    public function discharge(Request $request, int $stayId): JsonResponse
    {
        abort_unless(hms_can('ward.discharge'), 403);

        $data = $request->validate([
            'discharge_type' => ['required', 'in:'.implode(',', array_keys(stay::DISCHARGE_TYPES))],
            'discharge_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $stay = stay::findOrFail($stayId);

        if ($stay->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'That stay is already closed.',
            ], 422);
        }

        DB::transaction(function () use ($stay, $data, $request) {
            $stay->update([
                'status' => 'completed',
                'end_time' => now()->timestamp,
                'discharged_at' => now(),
                'discharge_type' => $data['discharge_type'],
                'discharge_note' => $data['discharge_note'] ?? null,
                'discharged_by' => $request->user()->id,
            ]);

            if ($stay->bed_id) {
                beds::where('id', $stay->bed_id)->update([
                    'patient_id' => null,
                    'status' => 'cleaning',
                    'discharge_time' => now(),
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Patient discharged, bed released for cleaning.',
            'data' => $stay->fresh(),
        ]);
    }

    public function labIndex(Request $request): JsonResponse
    {
        abort_unless(hms_can('lab'), 403);

        $status = $request->string('status')->toString();

        $reports = InvestigationReport::with([
            'test:id,name,code',
            'patient:id,name,age,gender',
            'appointment:id,patient_id,intime,status',
            'orderedBy:id,name',
            'reportedBy:id,name',
        ])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($request->integer('patient_id') > 0, fn ($q) => $q->where('patient_id', $request->integer('patient_id')))
            ->orderByDesc('is_urgent')
            ->orderByDesc('id')
            ->limit(min($request->integer('per_page', 25), 100))
            ->get();

        return $this->ok($reports);
    }

    public function labReport(Request $request, int $reportId): JsonResponse
    {
        abort_unless(hms_can('lab'), 403);

        $data = $request->validate([
            'findings' => ['nullable', 'string', 'max:2000'],
            'result' => ['nullable', 'string', 'max:2000'],
        ]);

        $report = InvestigationReport::findOrFail($reportId);

        if ($report->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'A cancelled order cannot be reported.',
            ], 422);
        }

        if (trim((string) ($data['findings'] ?? '')) === '' && trim((string) ($data['result'] ?? '')) === '') {
            return response()->json([
                'success' => false,
                'message' => 'Enter the findings or the result values.',
            ], 422);
        }

        $report->update([
            'findings' => $data['findings'] ?? null,
            'result' => $data['result'] ?? null,
            'status' => 'reported',
            'reported_by' => $request->user()->id,
            'reported_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Result reported.',
            'data' => $report->fresh(),
        ]);
    }

    /** Reception check-in / doctor status transitions on the OPD board. */
    public function appointmentStatus(Request $request, int $appointmentId): JsonResponse
    {
        abort_unless(hms_can('appointments'), 403);

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', appointment::STATUSES)],
        ]);

        $query = appointment::query();

        if ($request->user()->hasRole('doctor')) {
            $query->ownedBy((int) $request->user()->id);
        }

        $appt = $query->findOrFail($appointmentId);

        $appt->update([
            'status' => $data['status'],
            'outtime' => $data['status'] === 'completed' ? ($appt->outtime ?? now()) : $appt->outtime,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Appointment marked '.$data['status'].'.',
            'data' => $appt->fresh(),
        ]);
    }

    protected function ok($data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data]);
    }
}
