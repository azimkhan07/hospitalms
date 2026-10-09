<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\RecordsActivity;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class appointment extends Model
{
    use BelongsToTenant, HasFactory, RecordsActivity, SoftDeletes;

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
        'chief_complaint',
        'diagnosis',
        'follow_up_at',
    ];

    /** The OPD flow a receptionist and a doctor walk an appointment through. */
    public const STATUSES = [
        'pending', 'confirmed', 'waiting', 'in_consult',
        'completed', 'cancelled', 'terminated',
    ];

    protected $casts = [
        'intime' => 'datetime',
        'outtime' => 'datetime',
        'follow_up_at' => 'date',
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

    public function vitals()
    {
        return $this->hasMany(Vital::class);
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class);
    }

    /**
     * A doctor only ever reads their own appointments: rows whose doctor
     * profile is linked to this login (doctors.user_id).
     */
    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->whereHas(
            'doctor',
            fn (Builder $d) => $d->where('user_id', $userId)
        );
    }
}
