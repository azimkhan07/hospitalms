<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class contact extends Model
{
    use BelongsToTenant, HasFactory,SoftDeletes;
    protected $fillable=[
        'name',
        'email',
        'phone',
        'subject',
        'message',
    ];
}
