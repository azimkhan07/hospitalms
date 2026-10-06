<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class medicine extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'price',
        'quantity',
        'code',
        'name',
        'expiry_date',
        'batch_no',
        'manufacturer',
        'stock',
    ];

    protected $casts = [
        'expiry_date' => 'date',
    ];

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
}