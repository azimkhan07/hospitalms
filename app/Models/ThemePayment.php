<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThemePayment extends Model
{
    protected $fillable = [
        'tenant_id', 'theme_key', 'amount', 'currency', 'method',
        'reference', 'notes', 'paid_at', 'unlocked_by', 'revoked_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function theme()
    {
        return $this->belongsTo(Theme::class, 'theme_key', 'key');
    }

    public function unlockedBy()
    {
        return $this->belongsTo(User::class, 'unlocked_by');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at');
    }

    public function methodLabel(): string
    {
        return match ($this->method) {
            'cash' => 'Cash',
            'bank' => 'Bank transfer',
            'card' => 'Card',
            'online' => 'Online',
            default => 'Manual',
        };
    }
}
