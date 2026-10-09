<?php

namespace Tests\Feature;

use App\Models\appointment;
use App\Models\beds;
use App\Models\department;
use App\Models\doctor;
use App\Models\employee;
use App\Models\InvestigationReport;
use App\Models\InvestigationTest;
use App\Models\patient;
use App\Models\Role;
use App\Models\rooms;
use App\Models\stay;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vital;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The mobile app rides on the same rules as the panel: module gates per
 * role, tenant scoping on every list, and the OPD/IPD workflow endpoints
 * (PLAN.md sections 6, 7 and 17).
 */
class ClinicalApiTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Tenant $otherTenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'API Hospital', 'slug' => 'api-hospital',
            'mode' => 'hospital', 'status' => 'active', 'geo_radius_meters' => 200,
        ]);
        $this->tenant->syncRequiredRoles([
            'admin', 'moderator', 'receptionist', 'doctor', 'nurse',
            'laboratorist', 'pharmacist',
        ]);

        $this->otherTenant = Tenant::create([
            'name' => 'Other API Hospital', 'slug' => 'other-api-hospital',
            'mode' => 'hospital', 'status' => 'active', 'geo_radius_meters' => 200,
        ]);
        $this->otherTenant->syncRequiredRoles(['admin']);
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

    protected function make(string $model, array $attributes, ?Tenant $tenant = null)
    {
        $row = new $model;
        $row->tenant_id = ($tenant ?? $this->tenant)->id;
        $row->fill($attributes);
        $row->save();

        return $row;
    }

    protected function patientRow(string $name = 'API Patient'): patient
    {
        return $this->make(patient::class, [
            'name' => $name,
            'phone' => '0300-'.random_int(1000000, 9999999),
        ]);
    }

    protected function freeBed(): beds
    {
        $room = rooms::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'API Ward',
            'status' => 'available',
            'department_id' => department::create([
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
            ])->id,
        ]);

        return beds::create([
            'tenant_id' => $this->tenant->id,
            'room_id' => $room->id,
            'bed_number' => 'G1',
            'status' => 'available',
        ]);
    }

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

    // --- vitals -------------------------------------------------------------

    public function test_reception_posts_vitals_and_the_visit_moves_to_waiting(): void
    {
        $patient = $this->patientRow('API Vitals Patient');
        $profile = $this->doctorProfile($this->staff('doctor'));
        $appt = $this->appointmentRow($patient, $profile);

        $response = $this->actingAs($this->staff('receptionist'), 'sanctum')
            ->postJson('/api/v1/admin/vitals', [
                'patient_id' => $patient->id,
                'appointment_id' => $appt->id,
                'bp_systolic' => 120,
                'bp_diastolic' => 78,
                'pulse' => 70,
            ]);

        $response->assertStatus(201)->assertJsonPath('success', true);
        $this->assertSame($appt->id, $response->json('data.appointment_id'));
        $this->assertSame('waiting', $appt->fresh()->status);
        $this->assertSame(1, Vital::count());
    }

    public function test_a_pharmacist_is_shut_out_of_the_clinical_endpoints(): void
    {
        $patient = $this->patientRow();

        $this->actingAs($this->staff('pharmacist'), 'sanctum')
            ->postJson('/api/v1/admin/vitals', ['patient_id' => $patient->id, 'pulse' => 70])
            ->assertForbidden();

        $this->actingAs($this->staff('pharmacist'), 'sanctum')
            ->getJson('/api/v1/admin/stays')
            ->assertForbidden();

        $this->actingAs($this->staff('pharmacist'), 'sanctum')
            ->getJson('/api/v1/admin/lab-orders')
            ->assertForbidden();
    }

    // --- resource gates + tenant scope --------------------------------------

    public function test_the_staff_endpoint_stays_inside_your_own_facility(): void
    {
        User::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'role_id' => Role::where('slug', 'admin')->value('id'),
            'email' => 'hidden.admin@other-facility.test',
            'is_active' => true,
        ]);

        $mine = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('slug', 'nurse')->value('id'),
            'email' => 'visible.nurse@my-facility.test',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->staff('admin'), 'sanctum')
            ->getJson('/api/v1/admin/staff');

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertStringContainsString('visible.nurse@my-facility.test', $response->getContent());
        $this->assertStringNotContainsString('hidden.admin@other-facility.test', $response->getContent());
    }

    public function test_resource_endpoints_apply_the_same_modules_as_the_panel(): void
    {
        // A laboratorist has no business reading the staff directory.
        $this->actingAs($this->staff('laboratorist'), 'sanctum')
            ->getJson('/api/v1/admin/staff')
            ->assertForbidden();

        // Reception may read patients and appointments as usual.
        $this->actingAs($this->staff('receptionist'), 'sanctum')
            ->getJson('/api/v1/admin/patients')
            ->assertOk();

        $this->actingAs($this->staff('receptionist'), 'sanctum')
            ->getJson('/api/v1/admin/appointments')
            ->assertOk();
    }

    // --- IPD ----------------------------------------------------------------

    public function test_admission_and_discharge_follow_the_role_split(): void
    {
        $patient = $this->patientRow('Admit API Patient');
        $bed = $this->freeBed();

        // The nurse cannot hand out beds...
        $this->actingAs($this->staff('nurse'), 'sanctum')
            ->postJson('/api/v1/admin/stays/admit', ['patient_id' => $patient->id, 'bed_id' => $bed->id])
            ->assertForbidden();

        // ...the moderator (counter side) can.
        $admit = $this->actingAs($this->staff('moderator'), 'sanctum')
            ->postJson('/api/v1/admin/stays/admit', ['patient_id' => $patient->id, 'bed_id' => $bed->id]);

        $admit->assertStatus(201)->assertJsonPath('success', true);
        $stayId = $admit->json('data.id');
        $this->assertSame('alloted', $bed->fresh()->status);

        // The admin reviews but never discharges (same pattern as beds).
        $this->actingAs($this->staff('admin'), 'sanctum')
            ->postJson('/api/v1/admin/stays/'.$stayId.'/discharge', ['discharge_type' => 'recovered'])
            ->assertForbidden();

        // The nurse discharges with an outcome and the bed goes to cleaning.
        $discharge = $this->actingAs($this->staff('nurse'), 'sanctum')
            ->postJson('/api/v1/admin/stays/'.$stayId.'/discharge', [
                'discharge_type' => 'expired',
                'discharge_note' => 'Resuscitation failed.',
            ]);

        $discharge->assertOk()->assertJsonPath('data.discharge_type', 'expired');
        $this->assertSame('cleaning', $bed->fresh()->status);
        $this->assertNotNull(stay::find($stayId)->discharged_at);
    }

    // --- OPD status ---------------------------------------------------------

    public function test_a_doctor_cannot_move_another_doctors_appointment(): void
    {
        $patientA = $this->patientRow('Doctor A Patient');
        $patientB = $this->patientRow('Doctor B Patient');

        $doctorA = $this->staff('doctor');
        $doctorB = $this->staff('doctor');
        $apptA = $this->appointmentRow($patientA, $this->doctorProfile($doctorA));
        $apptB = $this->appointmentRow($patientB, $this->doctorProfile($doctorB));

        $this->actingAs($doctorA, 'sanctum')
            ->patchJson('/api/v1/admin/appointments/'.$apptA->id.'/status', ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertNotNull($apptA->fresh()->outtime);

        $this->actingAs($doctorA, 'sanctum')
            ->patchJson('/api/v1/admin/appointments/'.$apptB->id.'/status', ['status' => 'completed'])
            ->assertNotFound();

        $this->assertSame('confirmed', $apptB->fresh()->status);
    }

    // --- lab ----------------------------------------------------------------

    public function test_the_lab_reports_through_the_api(): void
    {
        $patient = $this->patientRow('Lab API Patient');
        $profile = $this->doctorProfile($this->staff('doctor'));
        $appt = $this->appointmentRow($patient, $profile);

        $test = InvestigationTest::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Lipid Profile',
            'base_rate' => 700,
            'calc_type' => 'flat',
            'is_active' => true,
        ]);

        $order = InvestigationReport::create([
            'tenant_id' => $this->tenant->id,
            'investigation_test_id' => $test->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appt->id,
            'status' => 'pending',
        ]);

        $laboratorist = $this->staff('laboratorist');

        $this->actingAs($laboratorist, 'sanctum')
            ->getJson('/api/v1/admin/lab-orders?status=pending')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->actingAs($laboratorist, 'sanctum')
            ->postJson('/api/v1/admin/lab-orders/'.$order->id.'/report', [
                'result' => 'Cholesterol 182 mg/dL',
                'findings' => 'Normal lipid profile.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'reported');

        $order->refresh();
        $this->assertSame($laboratorist->id, $order->reported_by);
    }
}
