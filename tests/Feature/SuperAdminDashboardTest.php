<?php

namespace Tests\Feature;

use App\Http\Livewire\SuperAdmin\Dashboard;
use App\Models\appointment;
use App\Models\payment;
use App\Models\patient;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Platform overview: read-across stats (patients / appointments / revenue /
 * staff) and the read-only system-health card (PLAN.md 14 / 0b F).
 */
class SuperAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $a;

    protected Tenant $b;

    protected User $super;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->a = Tenant::create(['name' => 'Alpha Hospital', 'slug' => 'alpha', 'mode' => 'hospital', 'status' => 'active', 'geo_radius_meters' => 200]);
        $this->b = Tenant::create(['name' => 'Beta Clinic', 'slug' => 'beta', 'mode' => 'clinic', 'status' => 'active', 'geo_radius_meters' => 200]);

        $this->super = User::factory()->create([
            'role_id' => Role::where('slug', 'super_admin')->value('id'),
            'is_active' => true,
        ]);

        foreach ([$this->a, $this->b] as $tenant) {
            $p = new patient;
            $p->tenant_id = $tenant->id;
            $p->fill(['name' => 'P '.$tenant->slug, 'phone' => '0300-'.random_int(1000000, 9999999)]);
            $p->save();

            $bill = new \App\Models\bill;
            $bill->tenant_id = $tenant->id;
            $bill->fill(['patients_id' => $p->id, 'amount' => 100, 'status' => 'paid']);
            $bill->save();

            $pay = new payment;
            $pay->tenant_id = $tenant->id;
            $pay->fill(['patient_id' => $p->id, 'bill_id' => $bill->id, 'amount' => 100, 'status' => 'paid', 'mode' => 'cash']);
            $pay->save();

            $ap = new appointment;
            $ap->tenant_id = $tenant->id;

            $person = new \App\Models\employee;
            $person->tenant_id = $tenant->id;
            $person->fill(['name' => 'Dr '.$tenant->slug, 'email' => 'dr-'.$tenant->slug.'@example.com', 'phone' => '0300-1111111', 'position' => 'doctor']);
            $person->save();

            $doctor = new \App\Models\doctor;
            $doctor->tenant_id = $tenant->id;
            $doctor->fill(['employee_id' => $person->id]);
            $doctor->save();

            $ap->fill(['patient_id' => $p->id, 'doctor_id' => $doctor->id, 'intime' => now()]);
            $ap->save();
        }
    }

    public function test_platform_dashboard_rolls_up_every_tenant(): void
    {
        Livewire::actingAs($this->super)->test(Dashboard::class)
            ->assertOk()
            ->assertViewHas('platform', function ($platform) {
                return $platform['patients'] === 2
                    && $platform['appointments_today'] === 2
                    && (float) $platform['revenue_today'] === 200.0;
            })
            ->assertSee('System health')
            ->assertSee('Audit trail');
    }

    public function test_system_health_reports_versions_and_no_snapshot(): void
    {
        Livewire::actingAs($this->super)->test(Dashboard::class)
            ->assertOk()
            ->assertSee(PHP_VERSION)
            ->assertSee('Queue driver')
            ->assertSee('Database size')
            ->assertSee('Storage used');
    }
}
