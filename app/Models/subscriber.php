<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class subscriber extends Model
{
    use BelongsToTenant, HasFactory,softDeletes;
    protected $fillable=[
        'email'
    ];
}
