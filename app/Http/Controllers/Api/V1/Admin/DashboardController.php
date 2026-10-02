<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\appointment;
use App\Models\employee;
use App\Models\medicine;
use App\Models\patient;
use App\Models\requestedAppointment;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'employees' => employee::count(),
                'patients' => patient::count(),
                'appointments' => appointment::count(),
                'requested_appointments' => requestedAppointment::count(),
                'expired_medicines' => medicine::whereNull('deleted_at')
                    ->whereNotNull('expiry_date')
                    ->whereDate('expiry_date', '<=', today())
                    ->count(),
            ],
        ]);
    }
}
