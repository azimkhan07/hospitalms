<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\appointment;
use App\Models\doctor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API mirror of the OPD queue board (PLAN.md section 18i): tokenised
 * check-in, the doctor's "call next" bell and the reception "send in"
 * acknowledgement, plus a doctor's duty toggle.
 */
class QueueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(hms_can('appointments'), 403);

        $query = appointment::with(['patient:id,name,age,gender,bloodgroup,phone', 'doctor.employ:id,name'])
            ->whereDate('intime', today())
            ->whereIn('status', ['waiting', 'called', 'in_consult'])
            ->orderBy('token');

        if ($request->user()->hasRole('doctor')) {
            $query->ownedBy((int) $request->user()->id);
        }

        $queue = $query->get();

        // Additive: each doctor entry also reports attendance for the day so
        // the app can show duty status alongside the queue.
        $queue->each(function (appointment $appt) {
            if ($appt->doctor) {
                $appt->doctor->setAttribute('attendance_today', (bool) $appt->doctor->onAttendanceToday());
            }
        });

        return response()->json([
            'success' => true,
            'data' => $queue,
        ]);
    }

    public function callNext(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole('doctor'), 403);

        $appt = appointment::query()
            ->ownedBy((int) $request->user()->id)
            ->where('status', 'waiting')
            ->whereDate('intime', today())
            ->orderBy('token')
            ->orderBy('intime')
            ->first();

        if (! $appt) {
            return response()->json([
                'success' => false,
                'message' => 'No patients waiting right now.',
            ], 422);
        }

        $appt->update(['status' => 'called', 'called_at' => now()]);
        $appt->notifyReceptionCall();

        return response()->json([
            'success' => true,
            'message' => 'Called token #'.($appt->token ?? '-').' — '.$appt->patient?->name.'.',
            'data' => $appt->fresh(['patient:id,name', 'doctor.employ:id,name']),
        ]);
    }

    public function sendIn(Request $request, int $appointmentId): JsonResponse
    {
        abort_unless(hms_can('appointments'), 403);

        if ($request->user()->hasRole('doctor')) {
            abort(403);
        }

        $appt = appointment::findOrFail($appointmentId);

        if ($appt->status !== 'called') {
            return response()->json([
                'success' => false,
                'message' => 'Only a called patient can be sent in.',
            ], 422);
        }

        $appt->update(['status' => 'in_consult']);

        return response()->json([
            'success' => true,
            'message' => 'Patient sent in to the doctor.',
            'data' => $appt->fresh(),
        ]);
    }

    public function toggleDuty(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole('doctor'), 403);

        $profile = doctor::where('user_id', $request->user()->id)->first();

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Your login is not linked to a doctor profile.',
            ], 422);
        }

        $profile->update(['on_duty' => ! $profile->on_duty]);

        return response()->json([
            'success' => true,
            'message' => $profile->on_duty ? 'You are now on duty.' : 'You are now off duty.',
            'data' => ['on_duty' => (bool) $profile->on_duty],
        ]);
    }
}