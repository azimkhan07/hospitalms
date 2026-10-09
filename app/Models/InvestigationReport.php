<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\InvestigationCharge;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One investigation recorded against a patient, optionally against the bed or
 * room the machine was used in.
 *
 * The money is calculated by InvestigationCharge and then frozen onto the row
 * together with the formula that produced it, so an old report keeps printing
 * the same number even after the rate card is repriced.
 */
class InvestigationReport extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'investigation_reports';

    protected $guarded = ['id'];

    protected $casts = [
        'units' => 'integer',
        'is_urgent' => 'boolean',
        'discount' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'charge' => 'decimal:2',
        'result' => 'array',
        'reported_at' => 'datetime',
    ];

    public const STATUSES = ['pending', 'in_progress', 'reported', 'cancelled'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(patient::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(appointment::class);
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(InvestigationTest::class, 'investigation_test_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function bed(): BelongsTo
    {
        return $this->belongsTo(beds::class, 'bed_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(rooms::class, 'room_id');
    }

    /** Recalculate from the current rate card and store the result. */
    public function recalculate(array $overrides = []): self
    {
        $calc = InvestigationCharge::for(
            $this->test,
            array_merge([
                'units' => $this->units,
                'is_urgent' => $this->is_urgent,
                'discount' => $this->discount,
                'tax_percent' => $this->tax_percent,
            ], $overrides)
        );

        $this->forceFill([
            'charge' => $calc->total(),
            'formula' => $calc->formula(),
        ])->save();

        return $this;
    }

    /** Where this investigation happened, for the bed and room reports. */
    public function getPlacementLabelAttribute(): string
    {
        if ($this->bed_id && $this->bed) {
            return 'Bed '.$this->bed->bed_number;
        }

        if ($this->room_id && $this->room) {
            // rooms are named ("ICU-1"), there is no room_number column.
            return 'Room '.($this->room->name ?: ('#'.$this->room->id));
        }

        return 'Not bed-bound';
    }
}