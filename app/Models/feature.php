<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class feature extends Model
{
    use BelongsToTenant;

    protected $table = 'features';

    protected $fillable = [
        'tenant_id',
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
