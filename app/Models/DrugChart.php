<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One medicine line on an admitted patient's drug chart (PLAN.md Phase 6):
 * what was ordered, by whom, and - through the administration log - when the
 * nurse actually gave it.
 */
class DrugChart extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public const STATUSES = ['active', 'completed', 'cancelled'];

    protected $fillable = [
        'stay_id',
        'patient_id',
        'medicine',
        'dosage',
        'frequency',
        'route',
        'duration_days',
        'start_date',
        'notes',
        'ordered_by',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
    ];

    public function administrations()
    {
        return $this->hasMany(DrugChartAdministration::class);
    }

    public function stay()
    {
        return $this->belongsTo(stay::class);
    }

    public function patient()
    {
        return $this->belongsTo(patient::class);
    }

    public function orderer()
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }
}
