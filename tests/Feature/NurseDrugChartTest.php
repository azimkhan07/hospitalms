<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\Consultations;
use App\Http\Livewire\Admins\NurseDrugCharts;
use App\Http\Livewire\Admins\NurseHandovers;
use App\Models\beds;
use App\Models\department;
use App\Models\DoctorAlert;
use App\Models\DrugChart;
use App\Models\DrugChartAdministration;
use App\Models\Handover;
use App\Models\patient;
use App\Models\Role;
use App\Models\rooms;
use App\Models\stay;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\DoctorAlertRaised;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 6 acceptance: the nurse charts a medicine on an admitted stay and
 * logs every administration, hands the shift over in writing, and escalates
 * a patient to the doctor (bell + desk alert card). Same module gates and
 * tenant stamping as the rest of the ward screens.
 */
class NurseDrugChartTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $nurse;

    protected User $doctorUser;

    protected User $reception;

    protected patient $patient;

    protected stay $stay;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Nursing Hospital', 'slug' => 'nursing-hospital',
            'mode' => 'hospital', 'status' => 'active', 'geo_radius_meters' => 200,
        ]);
        $this->tenant->syncRequiredRoles([
            'admin', 'moderator', 'receptionist', 'doctor', 'nurse', 'pharmacist',
        ]);

        $this->nurse = $this->staff('nurse');
        $this->doctorUser = $this->staff('doctor');
        $this->reception = $this->staff('receptionist');

        $this->patient = $this->make(patient::class, [
            'name' => 'Chart Patient',
            'phone' => '0300-'.random_int(1000000, 9999999),
        ]);

        $bed = $this->freeBed();

        $this->stay = $this->make(stay::class, [
            'patient_id' => $this->patient->id,
            'room_id' => $bed->room_id,
            'bed_id' => $bed->id,
            'start_time' => now()->timestamp,
            'status' => 'active',
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

    /** A chart line created the way production does it: through the screen. */
    protected function chartLine(): DrugChart
    {
        Livewire::actingAs($this->nurse)
            ->test(NurseDrugCharts::class)
            ->set('stay_id', $this->stay->id)
            ->set('medicine', 'Amoxicillin 500mg')
            ->set('dosage', '1 capsule')
            ->set('frequency', 'TDS')
            ->call('addChart')
            ->assertHasNoErrors();

        return DrugChart::firstOrFail();
    }

    public function test_the_nursing_pages_are_forbidden_without_the_ward_module(): void
    {
        $this->actingAs($this->reception);

        $this->get('/admin/nurse/drug-charts')->assertForbidden();
        $this->get('/admin/nurse/handovers')->assertForbidden();

        Livewire::test(NurseDrugCharts::class)->assertForbidden();
        Livewire::test(NurseHandovers::class)->assertForbidden();
    }

    public function test_a_nurse_charts_a_medicine_on_an_admitted_stay(): void
    {
        Livewire::actingAs($this->nurse)
            ->test(NurseDrugCharts::class)
            ->set('stay_id', $this->stay->id)
            ->set('medicine', 'Amoxicillin 500mg')
            ->set('dosage', '1 capsule')
            ->set('frequency', 'TDS')
            ->set('route', 'oral')
            ->call('addChart')
            ->assertHasNoErrors()
            ->assertSee('Drug chart line added.');

        $chart = DrugChart::firstOrFail();

        $this->assertSame($this->nurse->id, $chart->ordered_by);
        $this->assertSame($this->tenant->id, $chart->tenant_id);
        $this->assertSame($this->patient->id, $chart->patient_id);
        $this->assertSame($this->stay->id, $chart->stay_id);
        $this->assertSame('active', $chart->status);
    }

    public function test_administering_a_dose_records_given_time_and_nurse(): void
    {
        $chart = $this->chartLine();

        Livewire::actingAs($this->nurse)
            ->test(NurseDrugCharts::class)
            ->set('note', 'Given after food')
            ->call('administer', $chart->id, 'given')
            ->assertHasNoErrors()
            ->assertSee('Administration recorded: given.');

        $record = DrugChartAdministration::firstOrFail();

        $this->assertSame($chart->id, $record->drug_chart_id);
        $this->assertSame('given', $record->state);
        $this->assertNotNull($record->scheduled_time);
        $this->assertNotNull($record->given_at);
        $this->assertSame($this->nurse->id, $record->given_by);
        $this->assertSame('Given after food', $record->note);
    }

    public function test_completing_a_chart_line_flips_its_status(): void
    {
        $chart = $this->chartLine();

        Livewire::actingAs($this->nurse)
            ->test(NurseDrugCharts::class)
            ->call('completeChart', $chart->id)
            ->assertHasNoErrors();

        $this->assertSame('completed', $chart->fresh()->status);

        Livewire::actingAs($this->nurse)
            ->test(NurseDrugCharts::class)
            ->call('cancelChart', $chart->fresh()->id)
            ->assertHasNoErrors();

        $this->assertSame('cancelled', $chart->fresh()->status);
    }

    public function test_a_shift_handover_is_recorded_and_listed(): void
    {
        Livewire::actingAs($this->nurse)
            ->test(NurseHandovers::class)
            ->set('ward', 'Male Ward')
            ->set('to_role', 'doctor')
            ->set('notes', 'Bed G1 spiked fever at 2am, culture sent.')
            ->call('createHandover')
            ->assertHasNoErrors()
            ->assertSee('Handover recorded.');

        $handover = Handover::firstOrFail();

        $this->assertSame($this->nurse->id, $handover->from_user);
        $this->assertSame($this->tenant->id, $handover->tenant_id);
        $this->assertSame('Male Ward', $handover->ward);
        $this->assertSame('doctor', $handover->to_role);

        Livewire::actingAs($this->nurse)
            ->test(NurseHandovers::class)
            ->assertSee('Bed G1 spiked fever at 2am, culture sent.');
    }

    public function test_raising_an_alert_notifies_doctors_and_shows_on_the_page(): void
    {
        Livewire::actingAs($this->nurse)
            ->test(NurseDrugCharts::class)
            ->set('stay_id', $this->stay->id)
            ->set('alert_category', 'vitals')
            ->set('alert_message', 'BP dropping, needs a doctor now')
            ->set('alert_urgent', true)
            ->call('raiseAlert')
            ->assertHasNoErrors()
            ->assertSee('Doctor alerted.');

        Notification::assertSentTo($this->doctorUser, DoctorAlertRaised::class);

        $alert = DoctorAlert::firstOrFail();
        $this->assertSame($this->patient->id, $alert->patient_id);
        $this->assertSame($this->stay->id, $alert->stay_id);
        $this->assertSame($this->nurse->id, $alert->raised_by);
        $this->assertSame($this->tenant->id, $alert->tenant_id);
        $this->assertTrue((bool) $alert->is_urgent);

        Livewire::actingAs($this->nurse)
            ->test(NurseDrugCharts::class)
            ->assertSee('BP dropping, needs a doctor now');
    }

    public function test_the_nursing_api_mirrors_the_chart_and_alerts(): void
    {
        $this->actingAs($this->nurse, 'sanctum');

        $this->postJson('/api/v1/admin/nursing/drug-charts', [
            'medicine' => 'Ceftriaxone 1g',
            'stay_id' => $this->stay->id,
            'dosage' => '1 vial',
            'frequency' => 'OD',
        ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $chart = DrugChart::firstOrFail();
        $this->assertSame($this->tenant->id, $chart->tenant_id);
        $this->assertSame($this->nurse->id, $chart->ordered_by);

        $this->getJson('/api/v1/admin/nursing/drug-charts')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.medicine', 'Ceftriaxone 1g');

        $this->postJson('/api/v1/admin/nursing/drug-charts/'.$chart->id.'/administer', [
            'state' => 'given',
            'note' => 'IV run complete',
        ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->getJson('/api/v1/admin/nursing/drug-charts/'.$chart->id.'/administrations')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.state', 'given');

        $this->postJson('/api/v1/admin/nursing/handovers', [
            'notes' => 'Handed over to the night shift.',
            'ward' => 'Male Ward',
        ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->postJson('/api/v1/admin/nursing/alerts', [
            'category' => 'pain',
            'message' => 'Patient reports 8 of 10 abdominal pain',
            'stay_id' => $this->stay->id,
            'is_urgent' => true,
        ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->getJson('/api/v1/admin/nursing/alerts')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_urgent', true);

        $this->getJson('/api/v1/admin/nursing/handovers')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        Notification::assertSentTo($this->doctorUser, DoctorAlertRaised::class);

        $alertId = DoctorAlert::firstOrFail()->id;
        $this->postJson('/api/v1/admin/nursing/alerts/'.$alertId.'/ack')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNotNull(DoctorAlert::find($alertId)->resolved_at);

        // A front-desk login has no business on the nursing endpoints.
        $this->actingAs($this->reception, 'sanctum');
        $this->getJson('/api/v1/admin/nursing/drug-charts')->assertForbidden();
    }

    public function test_the_doctor_desk_lists_and_acknowledges_unresolved_alerts(): void
    {
        $this->make(DoctorAlert::class, [
            'patient_id' => $this->patient->id,
            'stay_id' => $this->stay->id,
            'raised_by' => $this->nurse->id,
            'category' => 'vitals',
            'message' => 'BP dropping, needs a doctor now',
            'is_urgent' => true,
        ]);

        $this->actingAs($this->doctorUser);

        Livewire::test(Consultations::class)
            ->assertSee('BP dropping, needs a doctor now')
            ->assertSee('URGENT');

        Livewire::test(Consultations::class)
            ->call('acknowledgeAlert', DoctorAlert::firstOrFail()->id)
            ->assertHasNoErrors()
            ->assertSee('Alert acknowledged.');

        $this->assertNotNull(DoctorAlert::firstOrFail()->resolved_at);
        $this->assertSame($this->doctorUser->id, DoctorAlert::firstOrFail()->resolved_by);

        Livewire::test(Consultations::class)
            ->assertDontSee('BP dropping, needs a doctor now');
    }
}
