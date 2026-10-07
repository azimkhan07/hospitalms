<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A cardiac Angiography / cath lab machine owned by the facility.
 *
 * Once an angio machine is registered, the counter can attach a treatment
 * (appointment) to it, which is what the patient-list "Angio treatment"
 * filter looks at.
 */
class AngioMachine extends Model
{
    use BelongsToTenant, HasFactory, RecordsActivity, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'installed_at' => 'date',
        'rate' => 'decimal:2',
    ];

    public const STATUSES = ['working', 'under_service', 'retired'];

    public function appointments(): HasMany
    {
        return $this->hasMany(appointment::class, 'angio_machine_id');
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('status', 'working');
    }
}