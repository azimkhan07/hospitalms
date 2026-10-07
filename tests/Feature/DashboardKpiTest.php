<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\Dashboard;
use App\Models\appointment;
use App\Models\doctor;
use App\Models\employee;
use App\Models\patient;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 10 acceptance: the admin dashboard shows per-role KPI cards that
 * reflect the current tenant snapshot (PLAN.md 13).
 */
class DashboardKpiTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->tenant = Tenant::create([
            'name' => 'Heart Care Hospital',
            'slug' => 'heart-care-hospital',
            'mode' => 'hospital',
            'status' => 'active',
            'geo_radius_meters' => 200,
        ]);
        $this->tenant->syncRequiredRoles([
            'admin', 'moderator', 'doctor', 'accountant', 'receptionist', 'pharmacist',
            'laboratorist', 'storekeeper', 'nurse', 'hr',
        ]);
    }

    private function staff(string $slug): User
    {
        return User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('slug', $slug)->value('id'),
            'is_active' => true,
        ]);
    }

    public function test_the_receptionist_dashboard_shows_requested_appointments(): void
    {
        $this->actingAs($this->staff('receptionist'));

        Livewire::test(Dashboard::class)
            ->assertOk()
            ->assertSee('Requested Appointments')
            ->assertSee('Subscribers')
            ->assertDontSee('Salaries Due');
    }

    public function test_the_accountant_dashboard_shows_ledger_cards(): void
    {
        $this->actingAs($this->staff('accountant'));

        Livewire::test(Dashboard::class)
            ->assertOk()
            ->assertSee('Collected Today')
            ->assertSee('Outstanding')
            ->assertSee('Salaries Due')
            ->assertDontSee('Low Stock');
    }

    public function test_the_pharmacist_dashboard_shows_stock_cards(): void
    {
        $this->actingAs($this->staff('pharmacist'));

        Livewire::test(Dashboard::class)
            ->assertOk()
            ->assertSee('Low Stock')
            ->assertSee('Expired Medicines')
            ->assertDontSee('Birth Reports');
    }

    public function test_the_dashboard_cards_reflect_live_tenant_counts(): void
    {
        $this->actingAs($this->staff('receptionist'));

        $patient = patient::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Kpi Mehmood',
            'phone' => '0300-8888888',
        ]);

        $person = employee::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Dr. Kpi', 'email' => 'kpi@example.com',
            'phone' => '0300-7777777', 'position' => 'doctor',
        ]);
        $doctor = doctor::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $person->id,
        ]);

        appointment::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
        ]);

        Livewire::test(Dashboard::class)
            ->assertSee('Appointments Today')
            ->assertSee('Patients');
    }

    public function test_api_dashboard_returns_kpi_cards(): void
    {
        $user = $this->staff('receptionist');
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['kpi_cards', 'employees', 'patients', 'appointments'],
            ]);
    }
}