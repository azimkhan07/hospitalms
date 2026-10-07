<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\RecordsActivity;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class patient extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes, RecordsActivity;
    protected $fillable=[
        'name',
        'email',
        'phone',
        'address',
        'gender',
        'age',
        'bloodgroup',
        'photo_path',
    ];

    public function appointments()
    {
        return $this->hasMany(appointment::class);
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class);
    }

    public function stays()
    {
        return $this->hasMany(stay::class);
    }
}
