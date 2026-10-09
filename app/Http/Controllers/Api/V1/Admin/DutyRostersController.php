<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\DutyRoster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * API mirror of the weekly duty roster board. Automatically tenant-scoped by
 * the model's BelongsToTenant scope, which keys off the authed staff user.
 */
class DutyRostersController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(hms_can('attendance', $request->user()), 403);

        $from = $request->date('from') ?: now()->startOfWeek(Carbon::MONDAY);
        $to = $request->date('to') ?: now()->startOfWeek(Carbon::MONDAY)->addDays(6);

        $rows = DutyRoster::with('doctor.employ:id,name')
            ->whereBetween('duty_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('duty_date')
            ->orderBy('doctor_id')
            ->get()
            ->map(fn (DutyRoster $row): array => [
                'id' => $row->id,
                'doctor_id' => $row->doctor_id,
                'doctor_name' => $row->doctor?->employ?->name,
                'department' => $row->department,
                'shift' => $row->shift,
                'duty_date' => $row->duty_date->format('Y-m-d'),
                'start_time' => $row->start_time,
                'end_time' => $row->end_time,
                'note' => $row->note,
            ]);

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(hms_can('attendance', $request->user()), 403);

        $data = $request->validate([
            'doctor_id' => ['nullable', 'integer'],
            'department' => ['nullable', 'string', 'max:100'],
            'shift' => ['required', 'in:morning,evening,night,off'],
            'duty_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        if (empty($data['doctor_id']) && empty($data['department'])) {
            return response()->json([
                'success' => false,
                'message' => 'A doctor or department is required.',
            ], 422);
        }

        $doctorId = $data['doctor_id'] ?? null;

        $row = DutyRoster::updateOrCreate(
            [
                'tenant_id' => $request->user()->tenant_id,
                'doctor_id' => $doctorId,
                'department' => $doctorId ? null : ($data['department'] ?? null),
                'duty_date' => $data['duty_date'],
            ],
            [
                'shift' => $data['shift'],
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'note' => $data['note'] ?? null,
                'created_by' => $request->user()->id,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Duty roster entry saved.',
            'data' => [
                'id' => $row->id,
                'doctor_id' => $row->doctor_id,
                'department' => $row->department,
                'shift' => $row->shift,
                'duty_date' => $row->duty_date->format('Y-m-d'),
            ],
        ], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        abort_unless(hms_can('attendance', $request->user()), 403);

        // The tenant scope hides other facilities' rows, so a foreign id reads
        // as missing rather than being silently deletable.
        $row = DutyRoster::find($id);

        if (! $row) {
            return response()->json([
                'success' => false,
                'message' => 'Duty roster entry not found.',
            ], 404);
        }

        $row->delete();

        return response()->json([
            'success' => true,
            'message' => 'Duty roster entry removed.',
            'data' => ['id' => (int) $id],
        ]);
    }
}
