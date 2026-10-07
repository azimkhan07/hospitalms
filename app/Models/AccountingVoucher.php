<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountingVoucher extends Model
{
    use BelongsToTenant, HasFactory, RecordsActivity;

    public const INCOME = 'income';

    public const EXPENSE = 'expense';

    protected $fillable = [
        'type', 'title', 'note', 'amount', 'ref_type', 'ref_id',
        'occurred_at', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'occurred_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isIncome(): bool
    {
        return $this->type === self::INCOME;
    }
}