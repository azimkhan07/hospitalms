<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrescriptionItem extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'prescription_id', 'medicine', 'dosage',
        'frequency', 'duration', 'note',
    ];

    public function prescription()
    {
        return $this->belongsTo(Prescription::class);
    }
}