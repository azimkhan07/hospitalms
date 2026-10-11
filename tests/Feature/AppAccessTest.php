<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mobile app access is role-based and mode-aware: in hospital mode only the
 * moving roles (doctor, nurse, Dean/moderator, top admin) may log in to the
 * app; in clinic mode every role is app-enabled. The API login is the gate.
 */
class AppAccessTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Mode Hospital', 'slug' => 'mode-hospital',
            'mode' => 'hospital', 'status' => 'active', 'geo_radius_meters' => 200,
        ]);
        $this->tenant->syncRequiredRoles([
            'admin', 'moderator', 'doctor', 'nurse', 'receptionist', 'pharmacist',
            'accountant', 'hr', 'laboratorist', 'storekeeper',
        ]);
    }

    protected function staff(string $slug, ?Tenant $tenant = null): User
    {
        $tenant ??= $this->tenant;

        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role_id' => Role::where('slug', $slug)->value('id'),
            'is_active' => true,
        ]);
    }

    public function test_hospital_mode_app_roles_are_the_moving_roles_only(): void
    {
        foreach (['admin', 'moderator', 'doctor', 'nurse'] as $slug) {
            $this->assertTrue(hms_role_has_app($slug), $slug.' should be app-enabled in hospital mode');
        }

        foreach (['receptionist', 'pharmacist', 'accountant', 'laboratorist', 'storekeeper', 'hr'] as $slug) {
            $this->assertFalse(hms_role_has_app($slug), $slug.' should be desktop-only in hospital mode');
        }
    }

    public function test_clinic_mode_has_every_role_on_the_app(): void
    {
        $clinic = Tenant::create([
            'name' => 'Mode Clinic', 'slug' => 'mode-clinic',
            'mode' => 'clinic', 'status' => 'active', 'geo_radius_meters' => 100,
        ]);

        $this->actingAs($this->staff('receptionist', $clinic));

        foreach (['admin', 'moderator', 'doctor', 'nurse', 'receptionist', 'pharmacist'] as $slug) {
            $this->assertTrue(hms_role_has_app($slug), $slug.' should be app-enabled in clinic mode');
        }
    }

    public function test_api_login_blocks_desktop_only_role_in_hospital_mode(): void
    {
        $receptionist = $this->staff('receptionist');

        $this->postJson('/api/v1/auth/login', [
            'email' => $receptionist->email,
            'password' => 'password',
            'device_name' => 'mobile',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_api_login_allows_app_role_in_hospital_mode(): void
    {
        $doctor = $this->staff('doctor');

        $this->postJson('/api/v1/auth/login', [
            'email' => $doctor->email,
            'password' => 'password',
            'device_name' => 'mobile',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.role', 'doctor')
            ->assertJsonPath('data.user.app_enabled', true)
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_api_login_allows_desktop_role_in_clinic_mode(): void
    {
        $clinic = Tenant::create([
            'name' => 'Mode Clinic', 'slug' => 'mode-clinic',
            'mode' => 'clinic', 'status' => 'active', 'geo_radius_meters' => 100,
        ]);

        // ResolveTenant maps the request host to the clinic so the mode in play
        // is known before login (no signed-in user to ask yet).
        config(['hms.host_map' => ['127.0.0.1' => $clinic->id]]);

        $pharmacist = $this->staff('pharmacist', $clinic);

        $this->postJson('/api/v1/auth/login', [
            'email' => $pharmacist->email,
            'password' => 'password',
            'device_name' => 'mobile',
        ])->assertOk()
            ->assertJsonPath('data.user.app_enabled', true);
    }

    public function test_me_reports_app_enabled_flag_for_ward_roles(): void
    {
        $doctor = $this->staff('doctor');
        $token = $doctor->createToken('mobile')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.role', 'doctor')
            ->assertJsonPath('data.app_enabled', true);
    }
}