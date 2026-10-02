<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'mode', 'domain', 'subdomain', 'status',
        'phone', 'email', 'address', 'city', 'state', 'country', 'working_hours',
        'logo', 'hero_image', 'facilities', 'trial_ends_at', 'created_by',
    ];

    protected $casts = [
        'facilities' => 'array',
        'trial_ends_at' => 'datetime',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function admins()
    {
        return $this->hasMany(User::class)->whereRelation('role', 'slug', 'admin');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function modeLabel(): string
    {
        return config("hms.modes.{$this->mode}.label") ?? ucfirst((string) $this->mode);
    }

    public function isHospital(): bool
    {
        return $this->mode === 'hospital';
    }

    public function getLogoUrlAttribute(): string
    {
        return storage_url($this->logo, 'default.png');
    }

    public function getHeroUrlAttribute(): ?string
    {
        return $this->hero_image ? storage_url($this->hero_image) : null;
    }

    public function facility(string $key, $default = null)
    {
        return $this->facilities[$key] ?? $default;
    }
}
