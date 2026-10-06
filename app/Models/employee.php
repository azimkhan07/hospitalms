<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class employee extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;
    protected $fillable = [
        "name",
        "email",
        "phone",
        "salary",
        "address",
        "qualification",
        "position",
        "status",
        "image",
    ];

}
