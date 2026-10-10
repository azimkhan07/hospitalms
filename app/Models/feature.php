<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class feature extends Model
{
    protected $table = 'features';

    protected $fillable = [
        'icon',
        'title',
        'text',
        'sort_order',
        'active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'active' => 'boolean',
    ];
}