<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class stay extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'room_id',
        'bed_id',
        'start_time',
        'end_time',
        'status',
        'amount',
        'discount',
        'total',
        'discharged_at',
        'discharge_note',
        'discharge_type',
        'discharged_by',
    ];

    /** PLAN.md section 7: how the stay ended. 'normal' is the legacy word for recovered. */
    public const DISCHARGE_TYPES = [
        'recovered' => 'Recovered / Improved',
        'referred' => 'Referred',
        'expired' => 'Expired',
        'lama' => 'Taken away (LAMA / DAMA)',
        'transferred' => 'Transferred',
        'normal' => 'Recovered (legacy)',
        'absconded' => 'Absconded (legacy)',
    ];

    protected $casts = [
        'discharged_at' => 'datetime',
    ];

    /**
     * start_time / end_time are stored as unix-timestamp strings, so expose
     * them as Carbon instances for the date pickers and history views.
     */
    public function getStartTimeAttribute($value): ?Carbon
    {
        return $this->toDate($value);
    }

    public function getEndTimeAttribute($value): ?Carbon
    {
        return $this->toDate($value);
    }

    private function toDate($value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value);
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function patient()
    {
        return $this->belongsTo(patient::class);
    }

    public function room()
    {
        return $this->belongsTo(rooms::class);
    }

    public function dischargedBy()
    {
        return $this->belongsTo(User::class, 'discharged_by');
    }

    public function vitals()
    {
        return $this->hasMany(Vital::class);
    }

    /** Most recent observation set for the ward list. */
    public function latestVital()
    {
        return $this->hasOne(Vital::class)->latestOfMany('taken_at');
    }

    /**
     * The exact numbered bed this stay occupies, per PLAN.md section 9b.
     */
    public function bed()
    {
        return $this->belongsTo(beds::class, 'bed_id');
    }

    /**
     * Printable "Room / Bed" for lists and reports.
     */
    public function bedLabel(): string
    {
        $room = $this->room?->name ?: ($this->room_id ? 'Room #'.$this->room_id : '-');
        $bed = $this->bed?->label();

        return $bed ? $room.' / Bed '.$bed : $room;
    }
}
