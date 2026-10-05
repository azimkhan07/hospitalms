<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Query\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One test on the rate card: what it is called, what it costs and how that cost
 * grows. The charge is never typed into a report -- it is always derived from
 * here, so a report can never drift from the rate card (PLAN.md 9d.2).
 */
class InvestigationTest extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'investigation_tests';

    protected $guarded = ['id'];

    protected $casts = [
        'base_rate' => 'decimal:2',
        'per_unit_rate' => 'decimal:2',
        'urgent_factor' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /** How the charge grows. Kept short because it is printed on the bill. */
    public const CALC_TYPES = [
        'flat' => 'Flat fee',
        'per_unit' => 'Base + per unit',
        'machine_rate' => 'Machine rate per unit',
    ];

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(InvestigationReport::class, 'investigation_test_id');
    }

    /** A retired test stays on old reports but must not be offered again. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}