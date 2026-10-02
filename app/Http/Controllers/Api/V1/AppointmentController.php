<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\doctor;
use App\Models\requestedAppointment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function request(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['required', 'string', 'max:13'],
            'doctor_id' => ['required', 'exists:doctors,id'],
            'stime' => ['required', 'date', 'after:now'],
            'address' => ['required', 'string', 'max:150'],
            'message' => ['nullable', 'string', 'max:550'],
        ]);

        $appointment = requestedAppointment::create([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'],
            'doctor_id' => $data['doctor_id'],
            'stime' => $data['stime'],
            'address' => $data['address'],
            'message' => ($data['message'] ?? null) ?: 'Appointment request from mobile app.',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Your appointment request has been received. Our team will call you to confirm.',
            'data' => $appointment,
        ], 201);
    }

    public function doctors(): JsonResponse
    {
        $doctors = doctor::with('employ:id,name')
            ->get()
            ->sortBy(fn ($d) => $d->employ?->name)
            ->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->employ?->name,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $doctors,
        ]);
    }

    public function requested(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);

        $appointments = requestedAppointment::with('doctor')
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $appointments,
        ]);
    }
}
