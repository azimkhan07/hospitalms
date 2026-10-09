<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One assigned shift for a doctor (or department) on a date.
 */
class DutyRoster extends Model
{
    use BelongsToTenant;

    public const SHIFTS = ['morning', 'evening', 'night', 'off'];

    protected $fillable = [
        'tenant_id', 'doctor_id', 'department', 'shift', 'duty_date',
        'start_time', 'end_time', 'note', 'created_by',
    ];

    protected $casts = [
        'duty_date' => 'date',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(doctor::class, 'doctor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
