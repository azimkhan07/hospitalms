<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prescription extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['patient_id', 'doctor_id', 'notes', 'status', 'issued_at', 'dispensed_at', 'dispensed_by', 'payment_status'];

    protected $casts = [
        'issued_at' => 'datetime',
        'dispensed_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function items()
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function dispenser()
    {
        return $this->belongsTo(User::class, 'dispensed_by');
    }

    public function isDispensed(): bool
    {
        return ! is_null($this->dispensed_at);
    }

    /** All named lines handed over? A prescription is paid when every line moved. */
    public function isFullyDispensed(): bool
    {
        return $this->items->isNotEmpty()
            && $this->items->every(fn ($item) => $item->dispensed_qty > 0);
    }
}