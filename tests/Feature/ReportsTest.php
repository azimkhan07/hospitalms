<?php

namespace Tests\Feature;

use App\Models\bill;
use App\Models\medicine;
use App\Models\patient;
use App\Models\Role;
use App\Models\Settings;
use App\Models\Tenant;
use App\Models\User;
use App\Services\NotificationDigest;
use App\Services\ReportBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 10 acceptance: the reports hub shows fresh numbers for a date range,
 * exports the same numbers as CSV, mirrors them over the API, and pings the
 * management bell digest once a day (PLAN.md 13).
 */
class ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Reports Hospital', 'slug' => 'reports-hospital',
            'mode' => 'hospital', 'status' => 'active', 'geo_radius_meters' => 200,
        ]);
        $this->tenant->syncRequiredRoles([
            'admin', 'moderator', 'doctor', 'accountant', 'receptionist',
        ]);
    }

    protected function staff(string $slug): User
    {
        return User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('slug', $slug)->value('id'),
            'is_active' => true,
        ]);
    }

    protected function seededPatient(string $name, int $daysAgo): patient
    {
        auth()->login(User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('slug', 'admin')->value('id'),
            'is_active' => true,
        ]));

        $patient = new patient(['name' => $name, 'phone' => '0300-'.random_int(1000000, 9999999), 'status' => 'pending']);
        $patient->tenant_id = $this->tenant->id;
        $patient->created_at = Carbon::now()->subDays($daysAgo);
        $patient->updated_at = Carbon::now()->subDays($daysAgo);
        $patient->save();

        return $patient;
    }

    public function test_the_reports_hub_renders_kpis_for_the_month(): void
    {
        $this->seededPatient('New Patient', 1);
        $this->seededPatient('Old Patient', 40);

        $admin = $this->staff('admin');

        Livewire::actingAs($admin)
            ->test(\App\Http\Livewire\Admins\Reports::class)
            ->assertSeeHtml('Reports &amp; Analytics')
            ->assertSee('New patients')
            ->assertSee('Total patients');
    }

    public function test_the_date_range_slices_the_new_patient_count(): void
    {
        $this->seededPatient('This Week Who', 0);
        $this->seededPatient('Long Ago Who', 60);

        $this->actingAs($this->staff('admin'));

        $data = ReportBuilder::aggregate(
            Carbon::now()->startOfWeek()->toDateString(),
            Carbon::now()->toDateString()
        );

        $this->assertSame(1, $data['kpIs']['new_patients']);
        $this->assertSame(2, $data['patients']['total']);
    }

    public function test_outstanding_and_doctor_performance_fill_in(): void
    {
        $receptionist = $this->staff('receptionist');
        $this->actingAs($receptionist);

        $patient = patient::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Dues Payer', 'phone' => '0300-1111111',
        ]);

        $doctorStaff = \App\Models\employee::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Dr. Rehan', 'email' => 'rehan@example.com',
            'phone' => '0300-2222222', 'position' => 'doctor',
        ]);
        $doctorRow = \App\Models\doctor::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $doctorStaff->id,
        ]);

        bill::create([
            'tenant_id' => $this->tenant->id,
            'patients_id' => $patient->id,
            'amount' => 500, 'status' => 'unpaid',
        ]);

        \App\Models\appointment::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctorRow->id,
            'description' => 'Follow-up',
            'status' => 'confirmed',
        ]);

        $r = ReportBuilder::aggregate();

        $this->assertEquals(500.0, round($r['kpIs']['outstanding'], 2));
        $this->assertCount(1, $r['due_list']);
        $this->assertSame('Dr. Rehan', $r['doctor_performance'][0]['doctor']);
        $this->assertSame(1, $r['doctor_performance'][0]['visits']);
    }

    public function test_only_report_roles_see_the_hub(): void
    {
        $doctor = $this->staff('doctor');
        $this->actingAs($doctor);

        $this->get(route('admin_reports'))->assertForbidden();
    }

    public function test_pharmacy_low_stock_shows_on_the_hub(): void
    {
        $this->actingAs($this->staff('admin'));

        medicine::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Paracetamol 500', 'code' => 'PARA-1',
            'price' => '20', 'quantity' => '0', 'stock' => 0, 'reorder_level' => 50,
        ]);

        Livewire::actingAs($this->staff('admin'))
            ->test(\App\Http\Livewire\Admins\Reports::class)
            ->assertSee('Low stock')
            ->assertSee('1', true);
    }

    public function test_the_csv_export_streams_the_same_numbers(): void
    {
        $this->seededPatient('CSV Baby', 0);

        $this->actingAs($this->staff('admin'));

        $response = $this->get(route('admin_reports_download', ['from' => Carbon::now()->startOfMonth()->toDateString()]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8');

        $body = $response->getContent();

        $this->assertStringContainsString('"KPI"', $body);
        $this->assertStringContainsString('"new_patients"', $body);
        $this->assertStringContainsString('"Patients"', $body);
    }

    public function test_the_reports_api_mirrors_the_hub(): void
    {
        $this->seededPatient('Api Patient', 0);
        $this->actingAs($this->staff('admin'), 'sanctum');

        $this->getJson(route('api.v1.admin.reports', ['from' => Carbon::now()->startOfMonth()->toDateString()]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.patients.total', 1)
            ->assertJsonStructure([
                'data' => ['kpIs', 'patients', 'beds', 'revenue', 'due_list'],
            ]);
    }

    public function test_the_digest_pings_management_only_once_a_day(): void
    {
        $admin = $this->staff('admin');
        $receptionist = $this->staff('receptionist');
        $this->actingAs($admin);

        patient::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Bill Owed', 'phone' => '0300-9999999',
        ]);
        bill::create([
            'tenant_id' => $this->tenant->id,
            'patients_id' => patient::first()->id,
            'amount' => 300, 'status' => 'unpaid',
        ]);

        NotificationDigest::run();
        NotificationDigest::run();

        $adminCount = $admin->notifications()->count();
        $staffCount = $receptionist->notifications()->count();

        $this->assertSame(1, $adminCount, 'digest fires once per day');
        $this->assertSame(0, $staffCount, 'receptionist is not on the digest');

        $settings = Settings::where('key', 'reports_digest_last:'.$this->tenant->id)->value('value');
        $this->assertSame(Carbon::today()->toDateString(), $settings);
    }
}