<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class payment extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;
    protected $fillable = [
        'patient_id',
        'bill_id',
        'amount',
        'status',
        'mode',
    ];

    public function patient()
    {
        return $this->belongsTo(patient::class);
    }

    public function bill()
    {
        return $this->belongsTo(bill::class);
    }
}
