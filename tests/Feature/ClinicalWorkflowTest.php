<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\Appiontment;
use App\Http\Livewire\Admins\Beds as BedsPage;
use App\Http\Livewire\Admins\Consultations;
use App\Http\Livewire\Admins\DischargeHistory;
use App\Http\Livewire\Admins\LabOrders;
use App\Http\Livewire\Admins\RequestedAppointments;
use App\Http\Livewire\Admins\StaffDirectory;
use App\Http\Livewire\Admins\Vitals;
use App\Http\Livewire\Admins\Ward;
use App\Models\appointment;
use App\Models\beds;
use App\Models\department;
use App\Models\doctor;
use App\Models\employee;
use App\Models\InvestigationReport;
use App\Models\InvestigationTest;
use App\Models\patient;
use App\Models\requestedAppointment;
use App\Models\Role;
use App\Models\rooms;
use App\Models\stay;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vital;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 8 acceptance: the OPD chain (check-in vitals -> consult -> lab ->
 * treated) and the IPD chain (admit -> ward vitals -> discharge) run through
 * their proper roles (PLAN.md sections 6 and 7).
 */
class ClinicalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Clinical Hospital', 'slug' => 'clinical-hospital',
            'mode' => 'hospital', 'status' => 'active', 'geo_radius_meters' => 200,
        ]);
        $this->tenant->syncRequiredRoles([
            'admin', 'moderator', 'receptionist', 'doctor', 'nurse',
            'laboratorist', 'pharmacist',
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

    protected function make(string $model, array $attributes)
    {
        $row = new $model;
        $row->tenant_id = $this->tenant->id;
        $row->fill($attributes);
        $row->save();

        return $row;
    }

    protected function patientRow(string $name = 'Ward Patient'): patient
    {
        return $this->make(patient::class, [
            'name' => $name,
            'phone' => '0300-'.random_int(1000000, 9999999),
        ]);
    }

    /** A doctor profile with its staff login, as StaffDirectory now creates. */
    protected function doctorProfile(User $user): doctor
    {
        $employee = $this->make(employee::class, [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '0321-0000000',
            'position' => 'doctor',
            'status' => 'active',
        ]);

        return $this->make(doctor::class, [
            'employee_id' => $employee->id,
            'user_id' => $user->id,
        ]);
    }

    protected function appointmentRow(patient $patient, doctor $doctor, array $attrs = []): appointment
    {
        return $this->make(appointment::class, array_merge([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'intime' => now(),
            'status' => 'confirmed',
        ], $attrs));
    }

    protected function freeBed(): beds
    {
        $room = rooms::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'General-1',
            'status' => 'available',
            'department_id' => $this->department()->id,
        ]);

        return beds::create([
            'tenant_id' => $this->tenant->id,
            'room_id' => $room->id,
            'bed_number' => 'G1',
            'status' => 'available',
        ]);
    }

    protected function department(): department
    {
        return department::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Medicine',
            'description' => 'General medicine ward.',
            'photo_path' => '',
            'hod_id' => $this->staff('doctor')->id,
            'block_id' => \App\Models\block::create([
                'tenant_id' => $this->tenant->id,
                'blockname' => 'Main',
                'blockcode' => 1000,
            ])->id,
        ]);
    }

    // --- OPD: check-in ------------------------------------------------------

    public function test_reception_captures_vitals_and_the_patient_moves_to_waiting(): void
    {
        $patient = $this->patientRow('OPD Guest');
        $profile = $this->doctorProfile($this->staff('doctor'));
        $appt = $this->appointmentRow($patient, $profile, ['status' => 'confirmed']);

        $this->actingAs($this->staff('receptionist'));

        Livewire::test(Vitals::class, ['appointment' => (string) $appt->id])
            ->set('bpSystolic', '128')
            ->set('bpDiastolic', '84')
            ->set('pulse', '76')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Vitals recorded.');

        $vital = Vital::first();
        $this->assertNotNull($vital);
        $this->assertSame($patient->id, $vital->patient_id);
        $this->assertSame($appt->id, $vital->appointment_id);
        $this->assertSame('waiting', $appt->fresh()->status);
    }

    public function test_a_pharmacist_cannot_open_the_vitals_or_ward_screens(): void
    {
        $this->actingAs($this->staff('pharmacist'));

        Livewire::test(Vitals::class)->assertForbidden();
        Livewire::test(Ward::class)->assertForbidden();
        Livewire::test(LabOrders::class)->assertForbidden();
    }

    // --- OPD: the consult ---------------------------------------------------

    public function test_a_doctor_only_sees_their_own_appointments(): void
    {
        $mine = $this->patientRow('My Patient');
        $theirs = $this->patientRow('Other Patient');

        $doctorA = $this->staff('doctor');
        $doctorB = $this->staff('doctor');
        $profileA = $this->doctorProfile($doctorA);
        $profileB = $this->doctorProfile($doctorB);

        $this->appointmentRow($mine, $profileA);
        $this->appointmentRow($theirs, $profileB);

        $this->actingAs($doctorA);

        Livewire::test(Consultations::class)
            ->assertSee('My Patient')
            ->assertDontSee('Other Patient');

        Livewire::test(Appiontment::class)
            ->assertSee('My Patient')
            ->assertDontSee('Other Patient');
    }

    public function test_the_consultation_saves_findings_orders_a_lab_test_and_completes(): void
    {
        $patient = $this->patientRow('Chest Pain');
        $doctorUser = $this->staff('doctor');
        $profile = $this->doctorProfile($doctorUser);
        $appt = $this->appointmentRow($patient, $profile, ['status' => 'waiting']);

        $test = InvestigationTest::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'ECG Lead II',
            'base_rate' => 300,
            'calc_type' => 'flat',
            'is_active' => true,
        ]);

        $this->actingAs($doctorUser);
        Livewire::test(Consultations::class)
            ->call('open', $appt->id)
            ->set('chiefComplaint', 'Crushing chest pain since morning')
            ->set('diagnosis', 'Suspected unstable angina')
            ->set('followUpAt', today()->addWeek()->toDateString())
            ->call('saveConsult')
            ->assertHasNoErrors()
            ->assertSee('Consultation saved.');

        $appt->refresh();
        $this->assertSame('in_consult', $appt->status);
        $this->assertSame('Suspected unstable angina', $appt->diagnosis);

        Livewire::test(Consultations::class)
            ->call('open', $appt->id)
            ->set('orderTests', [$test->id])
            ->set('orderPriority', 'stat')
            ->call('orderInvestigations')
            ->assertHasNoErrors();

        $order = InvestigationReport::first();
        $this->assertNotNull($order);
        $this->assertSame('pending', $order->status);
        $this->assertSame($appt->id, $order->appointment_id);
        $this->assertSame($doctorUser->id, $order->ordered_by);
        $this->assertTrue($order->is_urgent);

        Livewire::test(Consultations::class)
            ->call('complete', $appt->id)
            ->assertSee('Treated:');

        $this->assertSame('completed', $appt->fresh()->status);
    }

    // --- IPD: admit / discharge --------------------------------------------

    public function test_reception_allocation_opens_a_stay_and_a_discharge_reaches_the_history(): void
    {
        $patient = $this->patientRow('Admitted Ali');
        $bed = $this->freeBed();

        $this->actingAs($this->staff('receptionist'));

        Livewire::test(BedsPage::class)
            ->set('allocatePatient', [(int) $bed->id => $patient->id])
            ->call('allocate', $bed->id)
            ->assertHasNoErrors();

        $stay = stay::first();
        $this->assertNotNull($stay, 'Allocating a bed must open an IPD stay.');
        $this->assertSame('active', $stay->status);
        $this->assertSame($bed->id, $stay->bed_id);
        $this->assertSame('alloted', $bed->fresh()->status);

        // The nurse closes the stay with an outcome; the admin then reads it.
        $this->actingAs($this->staff('nurse'));

        Livewire::test(Ward::class)
            ->call('openDischarge', $stay->id)
            ->set('dischargeType', 'recovered')
            ->set('dischargeNote', 'Symptoms settled, follow up in 7 days.')
            ->call('discharge')
            ->assertHasNoErrors();

        $stay->refresh();
        $this->assertSame('completed', $stay->status);
        $this->assertSame('recovered', $stay->discharge_type);
        $this->assertNotNull($stay->discharged_at);
        $this->assertSame('cleaning', $bed->fresh()->status);

        $this->actingAs($this->staff('admin'));

        Livewire::test(DischargeHistory::class)
            ->assertSee('Admitted Ali')
            ->assertSee('Recovered');
    }

    public function test_a_nurse_records_ward_vitals_against_the_stay(): void
    {
        $patient = $this->patientRow('Ward Vitals');
        $bed = $this->freeBed();

        $stay = $this->make(stay::class, [
            'patient_id' => $patient->id,
            'room_id' => $bed->room_id,
            'bed_id' => $bed->id,
            'start_time' => now()->timestamp,
            'status' => 'active',
        ]);

        $this->actingAs($this->staff('nurse'));

        Livewire::test(Vitals::class, ['stay' => (string) $stay->id])
            ->set('pulse', '92')
            ->set('temperature', '38.4')
            ->call('save')
            ->assertHasNoErrors();

        $vital = Vital::first();
        $this->assertSame($stay->id, $vital->stay_id);
        $this->assertSame($patient->id, $vital->patient_id);
    }

    // --- Lab ----------------------------------------------------------------

    public function test_the_laboratorist_reports_a_pending_order(): void
    {
        $patient = $this->patientRow('Lab Patient');
        $profile = $this->doctorProfile($this->staff('doctor'));
        $appt = $this->appointmentRow($patient, $profile);

        $test = InvestigationTest::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'CBC',
            'base_rate' => 450,
            'calc_type' => 'flat',
            'is_active' => true,
        ]);

        $doctorUser = $this->staff('doctor');

        $order = InvestigationReport::create([
            'tenant_id' => $this->tenant->id,
            'investigation_test_id' => $test->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appt->id,
            'ordered_by' => $doctorUser->id,
            'priority' => 'stat',
            'status' => 'pending',
        ]);

        $this->actingAs($this->staff('laboratorist'));

        Livewire::test(LabOrders::class)
            ->assertSee('CBC')
            ->call('start', $order->id)
            ->assertSee('Work started');

        $this->assertSame('in_progress', $order->fresh()->status);

        Livewire::test(LabOrders::class)
            ->call('openReport', $order->id)
            ->set('reportResult', 'Hb 13.2 g/dL, TLC 6100')
            ->set('reportFindings', 'Within normal limits.')
            ->call('saveResult')
            ->assertHasNoErrors();

        $order->refresh();
        $this->assertSame('reported', $order->status);
        $this->assertNotNull($order->reported_at);
        $this->assertSame('laboratorist', User::find($order->reported_by)->role->slug);
    }

    // --- Website requests ---------------------------------------------------

    public function test_approving_a_website_request_registers_the_patient_and_books_the_appointment(): void
    {
        $profile = $this->doctorProfile($this->staff('doctor'));

        $requestRow = $this->make(requestedAppointment::class, [
            'name' => 'Web Patient',
            'email' => 'web.patient@example.test',
            'phone' => '0333-1112223',
            'address' => 'Street 4',
            'message' => 'Fever for 3 days',
            'doctor_id' => $profile->id,
            'stime' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        $this->actingAs($this->staff('receptionist'));

        Livewire::test(RequestedAppointments::class)
            ->call('approve', $requestRow->id)
            ->assertHasNoErrors()
            ->assertSee('confirmed');

        $requestRow->refresh();
        $this->assertSame('approved', $requestRow->status);
        $this->assertNotNull($requestRow->appointment_id);

        $created = appointment::find($requestRow->appointment_id);
        $this->assertNotNull($created);
        $this->assertSame('confirmed', $created->status);
        $this->assertDatabaseHas('patients', ['phone' => '0333-1112223']);
    }

    // --- Staff directory ----------------------------------------------------

    public function test_creating_a_doctor_login_links_the_doctor_profile(): void
    {
        $this->actingAs($this->staff('admin'));

        Livewire::test(StaffDirectory::class)
            ->call('createStaff')
            ->set('name', 'Dr Link Test')
            ->set('email', 'dr.link@clinical.test')
            ->set('newRole', 'doctor')
            ->set('phone', '0321-9998887')
            ->set('password', 'secret123')
            ->set('password_confirmation', 'secret123')
            ->call('saveStaff')
            ->assertHasNoErrors();

        $user = User::where('email', 'dr.link@clinical.test')->first();
        $this->assertNotNull($user);

        $profile = doctor::where('user_id', $user->id)->first();
        $this->assertNotNull($profile, 'A doctor login must link to a doctors row.');
        $this->assertNotNull($profile->employ);
    }
}
