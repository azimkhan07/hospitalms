<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An ICD-10 diagnosis code.
 *
 * Deliberately NOT tenant-scoped: the codes are shared medical knowledge, so a
 * NULL tenant_id marks the global baseline and a non-null one marks a facility's
 * own addition. Callers narrow with scopeForTenant() to see the baseline plus
 * the signed-in facility's rows.
 */
class Icd10Code extends Model
{
    use HasFactory;

    protected $table = 'icd10_codes';

    protected $fillable = [
        'tenant_id',
        'code',
        'description',
        'chapter',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** A retired code stays on old consults but must not be offered again. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Match a code or a word in its description. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.mb_strtolower($term).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->whereRaw('LOWER(code) LIKE ?', [$like])
                ->orWhereRaw('LOWER(description) LIKE ?', [$like]);
        });
    }

    /** The global baseline plus the facility's own rows. */
    public function scopeForTenant(Builder $query, ?int $tenantId): Builder
    {
        return $query->where(function (Builder $q) use ($tenantId) {
            $q->whereNull('tenant_id');

            if ($tenantId) {
                $q->orWhere('tenant_id', $tenantId);
            }
        });
    }
}
