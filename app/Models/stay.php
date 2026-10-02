<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class stay extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'room_id',
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
}
