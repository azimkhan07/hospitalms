<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * One line of the stock ledger: a purchase (+) or return (+) adds, a dispense
 * (-) or write-off (-) takes. The balance lives on the medicine, but this row
 * is the proof -- every unit came in from somewhere and went out somewhere.
 */
class StockMovement extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'medicine_id', 'quantity', 'type', 'batch_no',
        'reference', 'user_id', 'note',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function medicine()
    {
        return $this->belongsTo(medicine::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isIn(): bool
    {
        return in_array($this->type, ['purchase', 'return'], true);
    }

    public function isOut(): bool
    {
        return in_array($this->type, ['dispense', 'adjustment', 'expiry'], true);
    }
}