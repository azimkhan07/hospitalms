<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Services\DeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveriesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! hms_deliveries_enabled()) {
            abort(404, 'Deliveries are not enabled for this facility.');
        }

        $orders = DeliveryOrder::with('patient:id,name,phone')
            ->when($request->string('status')->toString() !== '', fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (DeliveryOrder $o) => [
                'id' => $o->id,
                'order_type' => $o->order_type,
                'patient' => $o->patient?->name,
                'patient_phone' => $o->phone,
                'address' => $o->address,
                'rider_name' => $o->rider_name,
                'vehicle' => $o->vehicle,
                'status' => $o->status,
                'delivery_fee' => (float) $o->delivery_fee,
                'requested_at' => $o->requested_at?->toIso8601String(),
                'delivered_at' => $o->delivered_at?->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! hms_deliveries_enabled()) {
            abort(404, 'Deliveries are not enabled for this facility.');
        }

        $data = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'order_type' => ['required', 'in:medicine,lab,general'],
            'address' => 'required|max:255',
            'phone' => 'required|max:40',
            'delivery_fee' => 'nullable|numeric|min:0',
            'note' => 'nullable|max:255',
        ]);

        $order = app(DeliveryService::class)->create($data, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Delivery order created.',
            'data' => ['id' => $order->id, 'status' => $order->status],
        ], 201);
    }
}