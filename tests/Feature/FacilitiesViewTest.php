<?php

namespace Tests\Feature;

use App\Models\InvestigationTest;
use App\Models\Machine;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PLAN.md 2b/2c acceptance: the admin's Facilities tab (9c.3) lists every
 * platform facility and shows which ticked roles are still unstaffed; the
 * Super Admin export (9c.5) is CSV; and the mobile mirrors for machines, the
 * rate card and facilities all stay tenant-scoped.
 */
class FacilitiesViewTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Tenant $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->tenant = Tenant::create([
            'name' => 'Facility One',
            'slug' => 'facility-one',
            'mode' => 'hospital',
            'status' => 'active',
            'geo_radius_meters' => 200,
        ]);
        $this->tenant->syncRequiredRoles(['admin', 'pharmacist', 'doctor', 'receptionist', 'nurse', 'moderator']);
        $this->other = Tenant::create([
            'name' => 'Facility Two',
            'slug' => 'facility-two',
            'mode' => 'hospital',
            'status' => 'active',
            'geo_radius_meters' => 200,
        ]);
        // No admin yet — the NEW queue (§9c.3).
        $this->other->syncRequiredRoles(['admin', 'moderator', 'doctor', 'nurse']);
    }

    private function staff(string $slug): User
    {
        return User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('slug', $slug)->value('id'),
            'is_active' => true,
        ]);
    }

    public function test_admin_sees_platform_facilities_with_needs_admin_badge(): void
    {
        $this->actingAs($this->staff('admin'))
            ->get('/admin/facilities')
            ->assertOk()
            ->assertSee('Facility One')
            ->assertSee('Facility Two')
            ->assertSee('NEW')
            ->assertSee('No admin assigned yet');
    }

    public function test_roles_still_needed_are_badged_unstaffed(): void
    {
        $admin = $this->staff('admin');
        $this->actingAs($admin)->get('/admin/facilities')->assertOk();

        $this->assertDatabaseHas('tenant_role_requirements', [
            'tenant_id' => $this->other->id,
            'role_id' => Role::where('slug', 'nurse')->value('id'),
        ]);

        // Nobody staffs facility two yet, so every ticked role shows 0.
        $this->get('/admin/facilities')
            ->assertOk()
            ->assertSee('Nurse')
            ->assertSee('Moderator');
    }

    public function test_unassigned_filter_keeps_only_unassigned_facilities(): void
    {
        // Facility two still has no admin, so only it matches the filter.
        $this->actingAs($this->staff('admin'));

        $html = $this->get('/admin/facilities?unassignedOnly=1')->getContent();
        $this->assertStringNotContainsString('>Facility One<', $html);
        $this->assertStringContainsString('>Facility Two<', $html);
    }

    public function test_non_admin_staff_is_forbidden_on_facilities(): void
    {
        $this->actingAs($this->staff('pharmacist'))
            ->get('/admin/facilities')
            ->assertForbidden();
    }

    public function test_super_admin_export_streams_csv_with_queues(): void
    {
        $super = User::factory()->create([
            'role_id' => Role::where('slug', 'super_admin')->value('id'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($super)->get('/superadmin/tenants/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Facility One', $response->getContent());
        $this->assertStringContainsString('Facility Two', $response->getContent());
        // Facility two still needs an admin, so the CSV flags it.
        $this->assertStringContainsString('no', $response->getContent());
    }

    public function test_api_machines_mirror_is_tenant_scoped(): void
    {
        $admin = $this->staff('admin');
        $this->actingAs($admin);

        $machine = Machine::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'ECG Tester',
            'code' => 'ECG-01',
            'modality' => 'ECG',
            'department' => 'Cardiology',
            'status' => 'working',
            'rate' => 500,
        ]);
        Machine::create([
            'tenant_id' => $this->other->id,
            'name' => 'Other-Labs MRI',
            'code' => 'MRI-02',
            'modality' => 'MRI',
            'status' => 'working',
            'rate' => 9000,
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/v1/admin/machines')
            ->assertOk()
            ->assertJsonPath('success', true);

        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('ECG Tester'));
        $this->assertFalse($names->contains('Other-Labs MRI'));
        // PHP's json_encode drops the ".0" on whole floats (500.0 → 500).
        $this->assertEquals(500.0, collect($response->json('data'))->firstWhere('name', 'ECG Tester')['rate']);
    }

    public function test_api_rate_card_mirror_includes_formula(): void
    {
        $admin = $this->staff('admin');
        $this->actingAs($admin);

        $test = InvestigationTest::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'CBC',
            'code' => 'CBC-01',
            'base_rate' => 100,
            'per_unit_rate' => 25,
            'calc_type' => 'per_unit',
            'urgent_factor' => 1.5,
            'max_units' => 4,
            'is_active' => true,
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/v1/admin/investigations')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.name', 'CBC')
            ->assertJsonPath('data.0.formula', '100.00 + 25.00 x 1');

        $this->assertEquals(125.0, $response->json('data.0.charge_for_one'));
        $this->assertEquals(100.0, $response->json('data.0.base_rate'));
    }

    public function test_api_facilities_mirror_reports_missing_roles(): void
    {
        $admin = $this->staff('admin');
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/admin/facilities')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');

        $facilityTwo = collect($this->withToken($token)->getJson('/api/v1/admin/facilities')->json('data'))
            ->firstWhere('name', 'Facility Two');
        $this->assertFalse($facilityTwo['has_admin']);
        $this->assertContains('doctor', $facilityTwo['missing_roles']);
    }

    public function test_api_requires_auth(): void
    {
        $this->getJson('/api/v1/admin/machines')->assertUnauthorized();
        $this->getJson('/api/v1/admin/investigations')->assertUnauthorized();
        $this->getJson('/api/v1/admin/facilities')->assertUnauthorized();
    }
}