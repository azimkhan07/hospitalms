<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'role_id',
        'phone',
        'designation',
        'department',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function isAdmin(): bool
    {
        return in_array($this->roleSlug(), ['admin', 'moderator'], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->roleSlug() === 'admin';
    }

    public function roleSlug(): ?string
    {
        return $this->role?->slug ?? $this->role;
    }

    public function hasRole(string ...$slugs): bool
    {
        $role = $this->roleSlug();

        return $role !== null && in_array($role, $slugs, true);
    }
}