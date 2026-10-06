<?php

namespace Tests\Feature;

use App\Models\appointment;
use App\Models\bill;
use App\Models\doctor;
use App\Models\employee;
use App\Models\patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Role;
use App\Models\patientCheckup;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Two facilities share one database, so the question is not whether the data
 * is there -- it is whether a doctor at one hospital can open the other's
 * patients, appointments, prescriptions and bills.
 *
 * Every list in the panel now carries the facility as a global scope, so these
 * tests hold the screens to it: they render the actual Livewire components a
 * signed-in staff member uses, rather than querying the model directly, because
 * the leak would have been in the screen, not in the table.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $alpha;

    protected Tenant $beta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->alpha = $this->facility('Alpha Hospital', 'alpha-hospital');
        $this->beta = $this->facility('Beta Clinic', 'beta-clinic');
    }

    protected function facility(string $name, string $slug): Tenant
    {
        $tenant = Tenant::create([
            'name' => $name, 'slug' => $slug,
            'mode' => 'hospital', 'status' => 'active', 'geo_radius_meters' => 200,
        ]);
        $tenant->syncRequiredRoles(['admin', 'moderator', 'doctor', 'nurse']);

        return $tenant;
    }

    protected function staff(Tenant $tenant, string $role = 'doctor'): User
    {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role_id' => Role::where('slug', $role)->value('id'),
            'is_active' => true,
        ]);
    }

    /**
     * Rows written outside a session -- by a seeder, a console command or a
     * test -- have nobody signed in to stamp them, so the facility is named
     * explicitly. tenant_id is deliberately not mass assignable on these models.
     */
    protected function ownedBy(Tenant $tenant, string $model, array $attributes): \Illuminate\Database\Eloquent\Model
    {
        $row = new $model($attributes);
        $row->tenant_id = $tenant->id;
        $row->save();

        return $row;
    }

    /**
     * A patient and the clinical trail that hangs off them: an appointment, a
     * checkup, a prescription with an item, and a bill.
     */
    protected function patientWithRecords(Tenant $tenant, string $name): patient
    {
        /** @var patient $patient */
        $patient = $this->ownedBy($tenant, patient::class, ['name' => $name, 'phone' => '01700000000']);
        $doctor = $this->doctorFor($tenant);

        $this->ownedBy($tenant, appointment::class, [
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id,
            'intime' => now(), 'status' => 'confirmed',
        ]);
        $this->ownedBy($tenant, patientCheckup::class, [
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id,
            'description' => 'Follow up for '.$name, 'status' => 'completed',
        ]);

        // prescriptions.doctor_id points at the staff account, not at doctors.
        $prescription = $this->ownedBy($tenant, Prescription::class, [
            'patient_id' => $patient->id, 'doctor_id' => $this->staff($tenant)->id,
            'status' => 'issued', 'issued_at' => now(),
        ]);
        $this->ownedBy($tenant, PrescriptionItem::class, [
            'prescription_id' => $prescription->id, 'medicine' => 'Panadol', 'dosage' => '1 tab',
        ]);

        $this->ownedBy($tenant, bill::class, ['patients_id' => $patient->id, 'status' => 'unpaid']);

        return $patient;
    }

    /** Appointments must name a doctor, so each facility gets one. */
    protected function doctorFor(Tenant $tenant): doctor
    {
        $employee = $this->ownedBy($tenant, employee::class, [
            'name' => 'Doctor of '.$tenant->name,
            'email' => 'doc-'.$tenant->slug.'@test.local', 'phone' => '01700000001',
        ]);

        return $this->ownedBy($tenant, doctor::class, ['employee_id' => $employee->id]);
    }

    public function test_a_row_is_stamped_with_the_signed_in_facility_without_being_asked(): void
    {
        // The panel saves through a form that knows nothing about facilities.
        // If the stamp were left to each screen, the one that forgot would file
        // a record under the wrong hospital -- so the model stamps it.
        $this->actingAs($this->staff($this->alpha));

        $patient = patient::create(['name' => 'Unstamped Test']);

        $this->assertSame($this->alpha->id, $patient->fresh()->tenant_id);

        $prescription = Prescription::create(['patient_id' => $patient->id, 'status' => 'issued']);
        $item = PrescriptionItem::create(['prescription_id' => $prescription->id, 'medicine' => 'Seconal']);

        $this->assertSame($this->alpha->id, $item->fresh()->tenant_id);
    }

    public function test_the_patient_list_shows_only_your_own_patients(): void
    {
        $this->patientWithRecords($this->alpha, 'Alpha Patient');
        $this->patientWithRecords($this->beta, 'Beta Patient');

        $this->actingAs($this->staff($this->alpha, 'admin'));

        Livewire::test(\App\Http\Livewire\Admins\Patients::class)
            ->assertSee('Alpha Patient')
            ->assertDontSee('Beta Patient');
    }
    public function test_the_appointment_queue_shows_only_your_own_appointments(): void
    {
        $mine = $this->patientWithRecords($this->alpha, 'Alpha Patient');
        $theirs = $this->patientWithRecords($this->beta, 'Beta Patient');

        $this->actingAs($this->staff($this->alpha, 'admin'));

        Livewire::test(\App\Http\Livewire\Admins\Appiontment::class)
            ->assertSee($mine->name)
            ->assertDontSee($theirs->name);
    }

    public function test_the_prescription_list_shows_only_your_own_prescriptions(): void
    {
        $mine = $this->patientWithRecords($this->alpha, 'Alpha Patient');
        $theirs = $this->patientWithRecords($this->beta, 'Beta Patient');

        $this->actingAs($this->staff($this->alpha, 'doctor'));

        Livewire::test(\App\Http\Livewire\Admins\Prescriptions::class)
            ->assertSee($mine->name)
            ->assertDontSee($theirs->name);
    }

    public function test_the_bill_list_shows_only_your_own_bills(): void
    {
        $mine = $this->patientWithRecords($this->alpha, 'Alpha Patient');
        $theirs = $this->patientWithRecords($this->beta, 'Beta Patient');

        $this->actingAs($this->staff($this->alpha, 'admin'));

        Livewire::test(\App\Http\Livewire\Admins\Bills::class)
            ->assertSee($mine->name)
            ->assertDontSee($theirs->name);
    }

    public function test_the_patient_history_of_a_foreign_patient_cannot_be_opened(): void
    {
        $theirs = $this->patientWithRecords($this->beta, 'Beta Patient');

        $this->actingAs($this->staff($this->alpha, 'doctor'));

        // The list hides the row; this is the second door -- asking for it by id.
        Livewire::test(\App\Http\Livewire\Admins\PatientHistory::class, ['patientId' => $theirs->id])
            ->assertDontSee('Beta Patient');
    }

    public function test_the_platform_panel_still_sees_every_facility(): void
    {
        $this->patientWithRecords($this->alpha, 'Alpha Patient');
        $this->patientWithRecords($this->beta, 'Beta Patient');

        $super = User::factory()->create([
            'tenant_id' => null,
            'role_id' => Role::where('slug', 'super_admin')->value('id'),
            'is_active' => true,
        ]);

        $this->assertTrue($super->isPlatformAdmin());

        $this->actingAs($super);

        // The scope only applies to a facility's own staff, so the platform
        // panel -- which signs in without a facility -- still sees them all.
        $this->assertSame(2, patient::query()->count());
        $this->assertSame(2, patient::acrossTenants()->count());
    }
}
