<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\User;

/**
 * Phase 9 - the delivery desk.
 *
 * Orders ride a strict status rail the panel and the mobile app both walk:
 * pending -> assigned -> out_for_delivery -> delivered, with cancel allowed
 * until an order goes out. Every transition sets its timestamp and keeps the
 * order's <status> in sync, so the UI just never offers the next legal move.
 */
class DeliveryService
{
    public const TRANSITIONS = [
        DeliveryOrder::PENDING => [DeliveryOrder::ASSIGNED, DeliveryOrder::CANCELLED],
        DeliveryOrder::ASSIGNED => [DeliveryOrder::OUT_FOR_DELIVERY, DeliveryOrder::CANCELLED],
        DeliveryOrder::OUT_FOR_DELIVERY => [DeliveryOrder::DELIVERED],
        DeliveryOrder::DELIVERED => [],
        DeliveryOrder::CANCELLED => [],
    ];

    public function create(array $data, ?User $by = null): DeliveryOrder
    {
        $data['status'] = DeliveryOrder::PENDING;
        $data['requested_at'] = $data['requested_at'] ?? now();
        $data['created_by'] = $by?->id;

        return DeliveryOrder::create($data);
    }

    /**
     * Move an order to $to if that move is legal in the rail.
     */
    public function advance(DeliveryOrder $order, string $to, ?User $by = null): DeliveryOrder
    {
        if (! in_array($to, self::TRANSITIONS[$order->status] ?? [], true)) {
            throw new \RuntimeException(
                "Cannot move a '{$order->status}' delivery to '{$to}'."
            );
        }

        $order->status = $to;
        $order->dispatched_at = $to === DeliveryOrder::OUT_FOR_DELIVERY ? now() : $order->dispatched_at;
        $order->delivered_at = $to === DeliveryOrder::DELIVERED ? now() : $order->delivered_at;
        $order->save();

        return $order;
    }
}