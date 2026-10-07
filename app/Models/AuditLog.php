<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'user_id', 'action', 'auditable_type',
        'auditable_id', 'summary', 'old', 'new', 'ip',
    ];

    protected $casts = [
        'old' => 'array',
        'new' => 'array',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function auditable()
    {
        return $this->morphTo();
    }

    public function getActorAttribute(): ?string
    {
        return $this->user?->name ?? 'system';
    }
}