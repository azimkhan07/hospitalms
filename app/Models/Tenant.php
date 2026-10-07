<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'mode', 'clinic_type_id', 'domain', 'subdomain', 'status',
        'phone', 'email', 'address', 'city', 'state', 'country', 'working_hours',
        'logo', 'hero_image', 'facilities', 'trial_ends_at', 'created_by',
        'latitude', 'longitude', 'geo_radius_meters',
        'private_room_enabled', 'private_room_count',
        'deliveries_enabled',
    ];

    protected $casts = [
        'facilities' => 'array',
        'trial_ends_at' => 'datetime',
        'private_room_enabled' => 'boolean',
        'private_room_count' => 'integer',
        'clinic_type_id' => 'integer',
        'deliveries_enabled' => 'boolean',
    ];

    protected static ?Tenant $current = null;

    /**
     * The facility a request belongs to, resolved from the host/domain by the
     * ResolveTenant middleware (falls back to the signed-in user's tenant).
     */
    public static function current(): ?Tenant
    {
        return static::$current;
    }

    public static function setCurrent(?Tenant $tenant): void
    {
        static::$current = $tenant;
    }

    public static function currentId(): ?int
    {
        return static::$current?->id;
    }

    /**
     * Role slugs this facility was configured with (PLAN.md section 9c.2).
     *
     * @return array<int, string>
     */
    public static function requiredRoleSlugs(int $tenantId): array
    {
        return TenantRoleRequirement::where('tenant_id', $tenantId)
            ->join('roles', 'roles.id', '=', 'tenant_role_requirements.role_id')
            ->orderBy('roles.id')
            ->pluck('roles.slug')
            ->all();
    }

    public function requiredRoles()
    {
        return Role::whereIn('id', $this->requirements()->pluck('role_id'))->orderBy('id')->get();
    }

    public function requirements()
    {
        return $this->hasMany(TenantRoleRequirement::class);
    }

    public function hasRole(string $slug): bool
    {
        return in_array($slug, static::requiredRoleSlugs($this->id), true);
    }

    /**
     * Replace the ticked role list. Unknown or platform roles are ignored so a
     * forged payload cannot smuggle super_admin into a facility.
     *
     * @param  array<int, string>  $slugs
     */
    public function syncRequiredRoles(array $slugs): void
    {
        $wanted = array_values(array_intersect(
            array_unique($slugs),
            Role::where('slug', '!=', 'super_admin')->pluck('slug')->all()
        ));

        DB::table('tenant_role_requirements')->where('tenant_id', $this->id)->delete();

        $roleIds = Role::whereIn('slug', $wanted)->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('tenant_role_requirements')->insert([
                'tenant_id' => $this->id,
                'role_id' => $roleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function clinicType()
    {
        return $this->belongsTo(ClinicType::class, 'clinic_type_id');
    }

    public function typeLabel(): string
    {
        return $this->clinicType?->name ?? 'Not set';
    }

    /**
     * Does this facility charge for private rooms? Asked by the Super Admin at
     * onboarding (PLAN.md section 9b); a private room always holds one patient.
     */
    public function hasPrivateRooms(): bool
    {
        return (bool) $this->private_room_enabled && (int) $this->private_room_count > 0;
    }

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
