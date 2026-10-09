<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrescriptionItem extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'prescription_id', 'medicine', 'medicine_id', 'dosage',
        'frequency', 'duration', 'quantity', 'note',
    ];

    protected $casts = [
        'medicine_id' => 'integer',
        'quantity' => 'integer',
        'dispensed_qty' => 'integer',
        'dispensed_at' => 'datetime',
    ];

    public function prescription()
    {
        return $this->belongsTo(Prescription::class);
    }

    /**
     * The joined drug from the master. Kept distinct from the string field
     * `medicine` (the free-text name a doctor typed) so the blade can show
     * both inside one row.
     */
    public function drug()
    {
        return $this->belongsTo(medicine::class, 'medicine_id');
    }
}