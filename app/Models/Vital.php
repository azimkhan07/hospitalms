<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One observation set (BP, pulse, temperature, SpO2, weight/height) taken by
 * reception at check-in, the nurse on the ward or the doctor in consultation
 * (PLAN.md section 6).
 */
class Vital extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'appointment_id',
        'stay_id',
        'taken_by',
        'bp_systolic',
        'bp_diastolic',
        'pulse',
        'temperature',
        'spo2',
        'weight',
        'height',
        'note',
        'taken_at',
    ];

    protected $casts = [
        'taken_at' => 'datetime',
        'temperature' => 'decimal:1',
        'weight' => 'decimal:2',
        'height' => 'decimal:2',
        'bp_systolic' => 'integer',
        'bp_diastolic' => 'integer',
        'pulse' => 'integer',
        'spo2' => 'integer',
    ];

    public function patient()
    {
        return $this->belongsTo(patient::class);
    }

    public function appointment()
    {
        return $this->belongsTo(appointment::class);
    }

    public function stay()
    {
        return $this->belongsTo(stay::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'taken_by');
    }

    /** "120/80" or "-" when blood pressure was not taken. */
    public function bpLabel(): string
    {
        if (! $this->bp_systolic || ! $this->bp_diastolic) {
            return '-';
        }

        return $this->bp_systolic.'/'.$this->bp_diastolic;
    }
}
