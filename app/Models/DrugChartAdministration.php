<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One entry in the nurse's administration log: the moment a charted medicine
 * was actually given, skipped or refused at the bedside (PLAN.md Phase 6).
 */
class DrugChartAdministration extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATES = ['due', 'given', 'skipped', 'refused'];

    protected $fillable = [
        'drug_chart_id',
        'scheduled_time',
        'given_at',
        'given_by',
        'state',
        'note',
    ];

    protected $casts = [
        'scheduled_time' => 'datetime',
        'given_at' => 'datetime',
    ];

    public function drugChart()
    {
        return $this->belongsTo(DrugChart::class);
    }

    public function giver()
    {
        return $this->belongsTo(User::class, 'given_by');
    }
}
