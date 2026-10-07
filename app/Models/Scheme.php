<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A Government scheme (yojna) e.g. Ayushman Bharat / PM-JAY.
 *
 * Patients enrolled via `patients.scheme_id`; the money for a scheme-covered
 * treatment is paid by the state, and the accountant books the grant through
 * Accounting::bookIncome (ref_type = scheme_grant).
 */
class Scheme extends Model
{
    use BelongsToTenant, HasFactory, RecordsActivity, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'coverage_value' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public const COVERAGE_TYPES = ['percent', 'amount'];

    public function patients(): HasMany
    {
        return $this->hasMany(patient::class, 'scheme_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(appointment::class, 'scheme_id');
    }

    /** Human label for the coverage value, e.g. "80 %" or "Rs 50,000". */
    public function getCoverageLabelAttribute(): string
    {
        if ($this->coverage_value === null) {
            return '—';
        }

        if ($this->coverage_type === 'percent') {
            return rtrim(rtrim(number_format((float) $this->coverage_value, 2), '0'), '.').' %';
        }

        return 'Rs '.number_format((float) $this->coverage_value);
    }
}