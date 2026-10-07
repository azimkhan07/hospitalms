<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\RecordsActivity;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class appointment extends Model
{
    use BelongsToTenant, HasFactory, RecordsActivity;

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'angio_machine_id',
        'scheme_id',
        'intime',
        'outtime',
        'status',
        'notes',
        'description',
        'prescription',
    ];

    protected $casts = [
        'intime' => 'datetime',
        'outtime' => 'datetime',
    ];

    /**
     * The legacy `description` column is NOT NULL without a database default,
     * so seed a blank value for every insert.
     */
    protected $attributes = [
        'description' => '',
        'status' => 'pending',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->intime ??= now();
        });
    }

    public function patient()
    {
        return $this->belongsTo(patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(doctor::class);
    }

    public function angioMachine()
    {
        return $this->belongsTo(AngioMachine::class, 'angio_machine_id');
    }

    public function scheme()
    {
        return $this->belongsTo(Scheme::class, 'scheme_id');
    }

    public function checkups()
    {
        return $this->hasMany(patientCheckup::class);
    }
}
