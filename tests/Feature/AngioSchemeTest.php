<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\AngioMachines;
use App\Http\Livewire\Admins\Patients;
use App\Http\Livewire\Admins\Schemes;
use App\Models\AngioMachine;
use App\Models\appointment;
use App\Models\bill;
use App\Models\doctor;
use App\Models\employee;
use App\Models\patient;
use App\Models\Role;
use App\Models\Scheme;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AngioSchemeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

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
        $this->tenant->syncRequiredRoles(['admin', 'moderator', 'accountant', 'doctor', 'receptionist']);
    }

    private function staff(string $slug): User
    {
        return User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('slug', $slug)->value('id'),
            'is_active' => true,
        ]);
    }

    private function patient(string $name): patient
    {
        return patient::create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'phone' => '0300-'.random_int(1000000, 9999999),
        ]);
    }

    private function angularDoctor(): doctor
    {
        $person = employee::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Dr. Shahid', 'email' => 'shahid@example.com',
            'phone' => '0300-9999999', 'position' => 'doctor',
        ]);

        return doctor::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $person->id,
        ]);
    }

    public function test_the_moderator_adds_an_angio_machine(): void
    {
        $this->actingAs($this->staff('moderator'));

        Livewire::test(AngioMachines::class)
            ->call('createMachine')
            ->set('name', 'Cath Lab 1')
            ->set('code', 'ANG-01')
            ->set('manufacturer', 'Philips')
            ->set('model', 'Azurion 7')
            ->set('rate', '5000')
            ->call('saveMachine')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('angio_machines', [
            'name' => 'Cath Lab 1', 'code' => 'ANG-01',
            'status' => 'working', 'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_the_admin_reads_but_cannot_write_angio_machines(): void
    {
        $this->actingAs($this->staff('admin'));

        Livewire::test(AngioMachines::class)
            ->assertOk()
            ->call('createMachine')
            ->assertForbidden();
    }

    public function test_the_moderator_adds_a_scheme(): void
    {
        $this->actingAs($this->staff('moderator'));

        Livewire::test(Schemes::class)
            ->call('createScheme')
            ->set('name', 'Ayushman Bharat (PM-JAY)')
            ->set('code', 'PMJAY')
            ->set('provider', 'State Health Agency')
            ->set('coverageType', 'amount')
            ->set('coverageValue', '50000')
            ->call('saveScheme')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('schemes', [
            'name' => 'Ayushman Bharat (PM-JAY)', 'code' => 'PMJAY',
            'coverage_type' => 'amount', 'is_active' => true,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_the_accountant_books_scheme_income_to_the_ledger(): void
    {
        $scheme = Scheme::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'PMJAY', 'coverage_type' => 'amount', 'coverage_value' => 50000,
        ]);

        $this->actingAs($this->staff('accountant'));

        Livewire::test(Schemes::class)
            ->call('openIncome', $scheme->id)
            ->set('incomeAmount', '40000')
            ->set('incomeNote', 'Disbursement Apr')
            ->call('recordIncome')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('accounting_vouchers', [
            'type' => 'income',
            'ref_type' => 'scheme_grant',
            'ref_id' => $scheme->id,
            'amount' => 40000,
            'title' => 'Scheme grant · PMJAY',
        ]);
    }

    public function test_the_patient_list_filters_by_angio_treatment(): void
    {
        $this->actingAs($this->staff('moderator'));

        $angioPatient = $this->patient('Yusuf Angio');
        $plain = $this->patient('Rani Plain');
        $angularDoctor = $this->angularDoctor();

        appointment::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $angioPatient->id,
            'doctor_id' => $angularDoctor->id,
            'angio_machine_id' => AngioMachine::create([
                'tenant_id' => $this->tenant->id, 'name' => 'Cath Lab 9',
            ])->id,
        ]);

        appointment::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $plain->id,
            'doctor_id' => $angularDoctor->id,
            'description' => 'Follow-up',
        ]);

        Livewire::test(Patients::class)
            ->set('angioFilter', '1')
            ->assertSee('Yusuf Angio')
            ->assertDontSee('Rani Plain');
    }

    public function test_the_patient_list_filters_by_scheme(): void
    {
        $this->actingAs($this->staff('moderator'));

        $scheme = Scheme::create([
            'tenant_id' => $this->tenant->id, 'name' => 'PMJAY',
            'coverage_type' => 'amount',
        ]);
        $this->patient('Chanda Scheme')->update(['scheme_id' => $scheme->id]);
        $this->patient('Rani Army')->update(['scheme_id' => null]);

        Livewire::test(Patients::class)
            ->set('schemeFilter', (string) $scheme->id)
            ->assertSee('Chanda Scheme')
            ->assertDontSee('Rani Army');
    }

    public function test_an_appointment_can_be_done_on_an_angio_under_a_scheme(): void
    {
        $this->actingAs($this->staff('receptionist'));

        $patient = $this->patient('Tariq Angio');
        $doctor = $this->angularDoctor();
        $scheme = Scheme::create([
            'tenant_id' => $this->tenant->id, 'name' => 'PMJAY',
            'coverage_type' => 'amount',
        ]);
        $angio = AngioMachine::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Cath Lab 2',
        ]);

        Livewire::test(\App\Http\Livewire\Admins\Appiontment::class)
            ->set('patient', $patient->id)
            ->set('doctor', $doctor->id)
            ->set('angioMachineId', (string) $angio->id)
            ->set('schemeId', (string) $scheme->id)
            ->set('start_timeee', now()->toDateTimeLocalString())
            ->call('add_appointment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('appointments', [
            'patient_id' => $patient->id,
            'angio_machine_id' => $angio->id,
            'scheme_id' => $scheme->id,
        ]);
    }

    public function test_a_scheme_with_enrolled_patients_cannot_be_deleted(): void
    {
        $this->actingAs($this->staff('moderator'));

        $scheme = Scheme::create([
            'tenant_id' => $this->tenant->id, 'name' => 'PMJAY',
            'coverage_type' => 'amount',
        ]);
        $this->patient('Badri Scheme')->update(['scheme_id' => $scheme->id]);

        Livewire::test(Schemes::class)->call('deleteScheme', $scheme->id);

        $this->assertDatabaseHas('schemes', ['id' => $scheme->id, 'deleted_at' => null]);
    }
}