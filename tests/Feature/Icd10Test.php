<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\Consultations;
use App\Http\Livewire\Admins\Icd10;
use App\Models\appointment;
use App\Models\doctor;
use App\Models\employee;
use App\Models\Icd10Code;
use App\Models\patient;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\Icd10Seeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ICD-10 diagnosis coding: the seeded baseline directory, the admin CRUD page,
 * the API lookup/CRUD and the doctor attaching a code to a consult.
 */
class Icd10Test extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $admin;

    protected User $doctorUser;

    protected doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Coding Hospital', 'slug' => 'coding-hospital',
            'mode' => 'hospital', 'status' => 'active', 'geo_radius_meters' => 200,
        ]);
        $this->tenant->syncRequiredRoles([
            'admin', 'moderator', 'receptionist', 'doctor', 'nurse', 'pharmacist',
        ]);

        $this->admin = $this->staff('admin');
        $this->doctorUser = $this->staff('doctor');

        // Doctor rows are stamped by BelongsToTenant under a signed-in user, so
        // create them while the admin is the acting identity (mirrors prod).
        Auth::login($this->admin);

        $person = employee::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Dr. Rao', 'email' => 'rao@example.com',
            'phone' => '0300-7777777', 'position' => 'doctor',
        ]);

        $this->doctor = doctor::create([
            'employee_id' => $person->id,
            'user_id' => $this->doctorUser->id,
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

    protected function patient(string $name): patient
    {
        return patient::create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'phone' => '0300-'.random_int(1000000, 9999999),
        ]);
    }

    protected function appt(patient $patient, string $status): appointment
    {
        Auth::login($this->admin);

        return appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'intime' => now()->startOfDay()->addHours(9),
            'status' => $status,
        ]);
    }

    public function test_seeder_inserts_baseline_and_is_idempotent(): void
    {
        $this->seed(Icd10Seeder::class);

        $count = Icd10Code::count();
        $this->assertGreaterThanOrEqual(50, $count);
        $this->assertDatabaseHas('icd10_codes', ['code' => 'E11', 'tenant_id' => null]);

        $this->seed(Icd10Seeder::class);

        $this->assertSame($count, Icd10Code::count());
    }

    public function test_directory_page_renders_for_admin(): void
    {
        $this->seed(Icd10Seeder::class);

        $this->actingAs($this->admin)
            ->get(route('admin_icd10'))
            ->assertOk()
            ->assertSee('Search code or description', false)
            ->assertSee('A09');
    }

    public function test_search_filters_codes_by_description(): void
    {
        $this->seed(Icd10Seeder::class);

        Livewire::actingAs($this->admin)
            ->test(Icd10::class)
            ->set('search', 'diabetes')
            ->assertSee('E11')
            ->assertDontSee('J06.9');
    }

    public function test_api_creates_code_and_rejects_duplicate(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $this->postJson('/api/v1/admin/icd10', [
            'code' => 'X99',
            'description' => 'Local facility code',
            'chapter' => 'Local additions',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'X99');

        $this->assertDatabaseHas('icd10_codes', [
            'code' => 'X99',
            'tenant_id' => $this->tenant->id,
        ]);

        $this->postJson('/api/v1/admin/icd10', [
            'code' => 'X99',
            'description' => 'Duplicate attempt',
        ])->assertStatus(422);
    }

    public function test_doctor_consult_saves_icd_code(): void
    {
        $code = Icd10Code::create([
            'tenant_id' => null,
            'code' => 'E11',
            'description' => 'Type 2 diabetes mellitus',
            'chapter' => 'Endocrine',
            'is_active' => true,
        ]);

        $visit = $this->appt($this->patient('Ramesh'), 'in_consult');

        Livewire::actingAs($this->doctorUser)
            ->test(Consultations::class)
            ->call('open', $visit->id)
            ->assertSet('icd', '')
            ->set('icd', 'E11')
            ->call('saveConsult')
            ->assertHasNoErrors()
            ->call('open', $visit->id)
            ->assertSet('icd', 'E11');

        $this->assertSame($code->id, $visit->fresh()->icd10_id);
    }

    public function test_directory_forbidden_without_staff_module(): void
    {
        $nurse = $this->staff('nurse');

        $this->actingAs($nurse)
            ->get(route('admin_icd10'))
            ->assertForbidden();
    }

    public function test_api_delete_of_other_tenant_code_returns_404(): void
    {
        $other = Tenant::create([
            'name' => 'Other Hospital', 'slug' => 'other-hospital',
            'mode' => 'hospital', 'status' => 'active', 'geo_radius_meters' => 200,
        ]);

        $foreign = Icd10Code::create([
            'tenant_id' => $other->id,
            'code' => 'Z99',
            'description' => 'Another facility code',
            'chapter' => null,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->admin, ['*']);

        $this->deleteJson('/api/v1/admin/icd10/'.$foreign->id)
            ->assertNotFound()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('icd10_codes', ['id' => $foreign->id]);
    }
}
