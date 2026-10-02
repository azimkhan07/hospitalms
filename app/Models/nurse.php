<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class nurse extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'nurses';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'gender',
        'position',
        'qualification',
        'registered',
        'address',
        'photo_path',
    ];

    protected $casts = [
        'registered' => 'boolean',
    ];
}