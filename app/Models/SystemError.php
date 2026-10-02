<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemError extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'user_id', 'role', 'level', 'method', 'url',
        'exception_class', 'message', 'file', 'line', 'stack',
        'context', 'resolved_at',
    ];

    protected $casts = [
        'context' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolved_at');
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    public function getShortMessageAttribute(): string
    {
        $message = (string) $this->message;

        return \Illuminate\Support\Str::limit($message, 90);
    }
}
