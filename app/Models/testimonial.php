<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class testimonial extends Model
{
    use BelongsToTenant;

    protected $table = 'testimonials';

    protected $fillable = [
        'tenant_id',
        'name',
        'role',
        'quote',
        'photo',
        'sort_order',
        'active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'active' => 'boolean',
    ];
}
