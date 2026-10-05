<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A diagnostic machine the facility owns or leases.
 *
 * Attaching a machine to a room or a bed is what lets the ICU report answer
 * "what was used on bed 12" without anyone retyping it (PLAN.md 9d.3).
 */
class Machine extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'machines';

    protected $guarded = ['id'];

    protected $casts = [
        'purchase_date' => 'date',
        'warranty_ends_at' => 'date',
        'rate' => 'decimal:2',
    ];

    public const STATUSES = ['working', 'under_service', 'retired'];

    /** Modalities offered by default; free text is still accepted. */
    public const MODALITIES = [
        'ECG', 'MRI', 'CT Scan', 'X-Ray', 'Ultrasound', 'Doppler', 'Echo',
        'Audiometry', 'OCT', 'Slit Lamp', 'Fundus', 'Haematology',
        'Biochemistry', 'Microbiology', 'Serology', 'BP', 'Spirometry',
        'Treadmill', 'EEG', 'EMG', 'NCS', 'Dialysis', 'Mammography',
        'Bone Densitometry', 'Cystoscopy', 'Endoscopy',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(rooms::class, 'room_id');
    }

    public function bed(): BelongsTo
    {
        return $this->belongsTo(beds::class, 'bed_id');
    }

    public function tests(): HasMany
    {
        return $this->hasMany(InvestigationTest::class, 'machine_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(InvestigationReport::class, 'machine_id');
    }

    /** A machine that is out of service cannot be billed against. */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('status', 'working');
    }

    public function getLocationLabelAttribute(): string
    {
        if ($this->bed_id && $this->bed) {
            return 'Bed '.$this->bed->bed_number;
        }

        if ($this->room_id && $this->room) {
            return 'Room '.$this->room->room_number;
        }

        return (string) ($this->location ?: 'Unassigned');
    }

    /** Warranty has run out: worth showing before the counter promises it works. */
    public function getWarrantyExpiredAttribute(): bool
    {
        return $this->warranty_ends_at !== null
            && $this->warranty_ends_at->isPast();
    }
}