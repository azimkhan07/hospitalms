<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class operationreport extends Model
{
    use BelongsToTenant, HasFactory,SoftDeletes;
    protected $fillable=[
        "patient_id",
        "description",
        "doctor_id",
        "status",
    ];

    public function patient()
    {
        return $this->belongsTo(patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(doctor::class);
    }
}
