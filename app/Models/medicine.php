<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\RecordsActivity;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class medicine extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes, RecordsActivity;

    protected $fillable = [
        'price',
        'quantity',
        'code',
        'name',
        'generic',
        'composition',
        'expiry_date',
        'batch_no',
        'manufacturer',
        'supplier',
        'mfg_date',
        'mrp',
        'stock',
        'reorder_level',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'mfg_date' => 'date',
        'mrp' => 'decimal:2',
        'stock' => 'integer',
        'reorder_level' => 'integer',
    ];

    public function stockMovements()
    {
        return $this->hasMany(\App\Models\StockMovement::class)->latest();
    }

    /** True when this row is a batch that can still be dispensed. */
    public function isDispensable(): bool
    {
        return $this->name
            && ($this->stock ?? 0) > 0
            && (is_null($this->expiry_date) || $this->expiry_date->isAfter(\Illuminate\Support\Carbon::today()));
    }

    public function scopeUsable($query)
    {
        return $query->whereNull('deleted_at')
            ->whereNotNull('name')
            ->where(function ($q) {
                $q->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', today());
            })
            ->where(function ($q) {
                $q->whereNull('stock')->orWhere('stock', '>', 0);
            });
    }

    /** Low-stock warning: either below the reorder level, or out entirely. */
    public function isLow(): bool
    {
        $stock = $this->stock ?? 0;

        if ($stock <= 0) {
            return true;
        }

        if ($this->reorder_level !== null) {
            return $stock <= (int) $this->reorder_level;
        }

        // No level set: warn only when nearly out.
        return $stock <= 2;
    }
}