<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryOrder extends Model
{
    use BelongsToTenant, HasFactory, RecordsActivity;

    protected $table = 'deliveries';

    public const PENDING = 'pending';

    public const ASSIGNED = 'assigned';

    public const OUT_FOR_DELIVERY = 'out_for_delivery';

    public const DELIVERED = 'delivered';

    public const CANCELLED = 'cancelled';

    public const STATUSES = [
        self::PENDING,
        self::ASSIGNED,
        self::OUT_FOR_DELIVERY,
        self::DELIVERED,
        self::CANCELLED,
    ];

    protected $fillable = [
        'patient_id', 'order_type', 'reference_id', 'address', 'phone',
        'rider_name', 'vehicle', 'delivery_fee', 'status',
        'requested_at', 'dispatched_at', 'delivered_at', 'note', 'created_by',
    ];

    protected $casts = [
        'delivery_fee' => 'decimal:2',
        'requested_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(patient::class, 'patient_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return ucwords(str_replace('_', ' ', $this->status));
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [self::DELIVERED, self::CANCELLED], true);
    }
}