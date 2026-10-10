<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Notifications\DoctorAlertRaised;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Notification;

/**
 * An escalation raised from the ward (PLAN.md Phase 6): the nurse flags a
 * patient who needs a doctor, every doctor on the facility gets a red bell
 * entry and the alert disappears once somebody acknowledges it.
 */
class DoctorAlert extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public const CATEGORIES = ['emergency', 'vitals', 'pain', 'meds', 'nursing', 'lab', 'other'];

    protected $fillable = [
        'patient_id',
        'stay_id',
        'raised_by',
        'category',
        'message',
        'is_urgent',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'is_urgent' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(patient::class);
    }

    public function stay()
    {
        return $this->belongsTo(stay::class);
    }

    public function raiser()
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** Still waiting for a doctor's acknowledgement. */
    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolved_at');
    }

    /** "Pain" for the badges on the alert lists. */
    public function categoriesLabel(): string
    {
        return ucfirst(self::CATEGORIES[$this->category] ?? (string) $this->category);
    }

    /**
     * Ping every active doctor on this facility, mirroring how
     * appointment::notifyReceptionCall() reaches the front desk.
     */
    public function notifyDoctors(): void
    {
        $recipients = User::where('tenant_id', $this->tenant_id)
            ->where('is_active', true)
            ->whereHas('role', fn ($r) => $r->where('slug', 'doctor'))
            ->get();

        Notification::send($recipients, new DoctorAlertRaised($this));
    }
}
