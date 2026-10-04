<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

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
        'tenant_id',
        'created_by',
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

    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * The signed-in session this user currently has open, if any.
     */
    public function openAttendance()
    {
        return $this->attendances()->whereNull('check_out_at')->latest('check_in_at')->first();
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
        return in_array($this->roleSlug(), ['admin', 'super_admin'], true);
    }

    public function isPlatformAdmin(): bool
    {
        return $this->roleSlug() === 'super_admin';
    }

    public function getIsSuperAdminAttribute(): bool
    {
        return $this->isSuperAdmin();
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