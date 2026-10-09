<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class requestedAppointment extends Model
{
    use BelongsToTenant, HasFactory,softDeletes;
    protected $fillable=[
        'name',
        'email',
        'phone',
        'doctor_id',
        'message',
        'address',
        'stime',
        'status',
        'patient_id',
        'appointment_id',
    ];

    public function doctor()
    {
        return $this->belongsTo(doctor::class);
    }

    public function patient()
    {
        return $this->belongsTo(patient::class);
    }

    public function appointment()
    {
        return $this->belongsTo(appointment::class);
    }
}
