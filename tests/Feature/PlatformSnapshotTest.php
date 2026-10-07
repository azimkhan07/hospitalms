<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 11: the platform API exposes a read-only, cross-tenant snapshot that
 * counts each facility's footprint in one call (PLAN.md 14, "Super Admin
 * cross-tenant access").
 */
class PlatformSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    protected function tenant(string $slug): Tenant
    {
        return Tenant::create([
            'name' => ucfirst($slug), 'slug' => $slug,
            'mode' => 'hospital', 'status' => 'active',
        ]);
    }

    protected function patient(int $tenantId, string $name): patient
    {
        $p = new patient(['name' => $name, 'phone' => '0300-2223333', 'status' => 'pending']);
        $p->tenant_id = $tenantId;
        $p->save();

        return $p;
    }

    protected function superAdmin(): User
    {
        return User::factory()->create([
            'role_id' => Role::where('slug', 'super_admin')->value('id'),
            'is_active' => true,
        ]);
    }

    public function test_the_snapshot_counts_every_tenant_without_auth_context(): void
    {
        $one = $this->tenant('alpha-hospital');
        $two = $this->tenant('beta-hospital');

        $this->patient($one->id, 'Alpha One');
        $this->patient($one->id, 'Alpha Two');
        $this->patient($two->id, 'Beta One');

        $super = $this->superAdmin();

        $response = $this->actingAs($super, 'sanctum')->getJson('/api/v1/superadmin/snapshot');

        $response->assertOk()->assertJsonPath('success', true);

        $tenants = collect($response->json('data.tenants'));
        $alpha = $tenants->firstWhere('id', $one->id);
        $beta = $tenants->firstWhere('id', $two->id);

        $this->assertSame(2, $alpha['counts']['patients']);
        $this->assertSame(1, $beta['counts']['patients']);
        $this->assertSame(3, $response->json('data.totals.patients'));
    }

    public function test_the_snapshot_needs_a_super_admin_token(): void
    {
        $this->getJson('/api/v1/superadmin/snapshot')->assertUnauthorized();

        $staff = User::factory()->create([
            'role_id' => Role::where('slug', 'admin')->value('id'),
            'is_active' => true,
        ]);

        $this->actingAs($staff, 'sanctum')->getJson('/api/v1/superadmin/snapshot')->assertForbidden();
    }
}