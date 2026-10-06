<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryVoucher extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'employee_id', 'gross', 'deductions', 'net', 'month',
        'status', 'paid_at', 'note', 'created_by',
    ];

    protected $casts = [
        'gross' => 'decimal:2',
        'deductions' => 'decimal:2',
        'net' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(\App\Models\employee::class, 'employee_id');
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}