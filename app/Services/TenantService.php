<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantService
{
    /**
     * Create a tenant (hospital/clinic) and, optionally, its first admin.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $admin
     */
    public function create(array $data, ?array $admin = null): Tenant
    {
        return DB::transaction(function () use ($data, $admin) {
            $data = $this->prepare($data);
            $data['created_by'] = $data['created_by'] ?? auth()->id();

            $tenant = Tenant::create($data);

            if ($admin && ! empty($admin['email'])) {
                $this->createAdmin($tenant, $admin);
            }

            return $tenant;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Tenant $tenant, array $data): Tenant
    {
        $tenant->update($this->prepare($data, $tenant));

        return $tenant->refresh();
    }

    /**
     * @param  array<string, mixed>  $admin
     */
    public function createAdmin(Tenant $tenant, array $admin): User
    {
        $roleId = Role::where('slug', 'admin')->value('id');

        if (! $roleId) {
            throw ValidationException::withMessages([
                'admin_email' => 'The "admin" role is missing. Run the seeders first.',
            ]);
        }

        return User::updateOrCreate(
            ['email' => $admin['email']],
            [
                'name' => $admin['name'] ?? 'Administrator',
                'password' => bcrypt($admin['password'] ?? '123456'),
                'role_id' => $roleId,
                'designation' => 'Administrator',
                'department' => 'Administration',
                'is_active' => true,
                'tenant_id' => $tenant->id,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data, ?Tenant $existing = null): array
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name'] ?? 'tenant');
        }

        $data['slug'] = $this->uniqueSlug($data['slug'], $existing?->id);

        if (array_key_exists('domain', $data) && $data['domain'] === '') {
            $data['domain'] = null;
        }

        if (array_key_exists('subdomain', $data) && $data['subdomain'] === '') {
            $data['subdomain'] = null;
        }

        return $data;
    }

    protected function uniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $slug = Str::slug($slug) ?: 'tenant';
        $base = $slug;
        $i = 2;

        while (Tenant::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
