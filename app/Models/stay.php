<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class stay extends Model
{
    use BelongsToTenant, HasFactory;

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
