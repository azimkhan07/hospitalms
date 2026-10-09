<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class beds extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    /**
     * Bed states. 'reserved' holds a bed for a planned admission and
     * 'cleaning' blocks reuse until a nurse has cleared it.
     */
    public const STATUSES = ['available', 'alloted', 'reserved', 'cleaning', 'maintenance'];

    protected $fillable = [
        'tenant_id',
        'room_id',
        'bed_number',
        'patient_id',
        'status',
        'alloted_time',
        'discharge_time',
    ];

    protected $casts = [
        'alloted_time' => 'datetime',
        'discharge_time' => 'datetime',
    ];

    /**
     * Printable label, e.g. "G1", "ICU2", "P3".
     */
    public function label(): string
    {
        return (string) ($this->bed_number ?: ('#'.$this->id));
    }

    /**
     * Can this bed be handed to a new patient right now?
     */
    public function isAllocatable(): bool
    {
        return $this->status === 'available' && $this->patient_id === null;
    }

    public function room()
    {
        return $this->belongsTo(rooms::class, 'room_id');
    }
    public function patient()
    {
        return $this->belongsTo(patient::class);
    }
    public function stays()
    {
        return $this->hasMany(stay::class, 'bed_id');
    }
}
