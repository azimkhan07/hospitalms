<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class bill extends Model
{
    use BelongsToTenant, HasFactory;
    protected $fillable=[
        'patients_id',
        'status',
        'amount',
        'tax',
        'discount',
        'invoice_no',
        'paid_at',
        'issued_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function patient(){
        return $this->belongsTo(patient::class, 'patients_id');
    }

    public function payments()
    {
        return $this->hasMany(payment::class, 'bill_id');
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function amountDue(): float
    {
        return max(0.0, (float) $this->amount - (float) $this->payments()->where('status', 'paid')->sum('amount'));
    }
}
