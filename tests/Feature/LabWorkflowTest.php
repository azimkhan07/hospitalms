<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\LabOrders;
use App\Models\DoctorAlert;
use App\Models\InvestigationReport;
use App\Models\InvestigationTest;
use App\Models\patient;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\DoctorAlertRaised;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Lab sample workflow: barcode labels on collection, the printable slip,
 * result-file upload and the critical-result escalation to the doctors' bell.
 * Mirrors the same module gates and tenant stamping as the rest of the panel.
 */
class LabWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $lab;

    protected User $doctorUser;

    protected User $reception;

    protected patient $patient;

    protected InvestigationTest $test;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Lab Hospital', 'slug' => 'lab-hospital',
            'mode' => 'hospital', 'status' => 'active', 'geo_radius_meters' => 200,
        ]);
        $this->tenant->syncRequiredRoles([
            'admin', 'moderator', 'receptionist', 'doctor', 'nurse', 'laboratorist', 'pharmacist',
        ]);

        $this->lab = $this->staff('laboratorist');
        $this->doctorUser = $this->staff('doctor');
        $this->reception = $this->staff('receptionist');

        $this->patient = $this->make(patient::class, [
            'name' => 'Lab Patient',
            'phone' => '0300-'.random_int(1000000, 9999999),
        ]);

        $this->test = InvestigationTest::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Complete Blood Count',
            'code' => 'CBC',
            'base_rate' => 500,
            'calc_type' => 'flat',
            'is_active' => true,
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

    protected function report(array $attributes = []): InvestigationReport
    {
        return InvestigationReport::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'investigation_test_id' => $this->test->id,
            'patient_id' => $this->patient->id,
            'status' => 'pending',
        ], $attributes));
    }

    public function test_collect_sample_stamps_a_unique_barcode_and_timestamp(): void
    {
        $first = $this->report();
        $second = $this->report();

        Livewire::actingAs($this->lab)->test(LabOrders::class)
            ->call('collectSample', $first->id)
            ->assertHasNoErrors();

        Livewire::actingAs($this->lab)->test(LabOrders::class)
            ->call('collectSample', $second->id)
            ->assertHasNoErrors();

        $first->refresh();
        $second->refresh();

        $this->assertNotNull($first->barcode);
        $this->assertStringStartsWith('LAB-'.$this->tenant->id.'-', $first->barcode);
        $this->assertNotNull($first->sample_collected_at);
        $this->assertSame($this->lab->id, $first->sample_collected_by);
        $this->assertNotSame($first->barcode, $second->barcode);
    }

    public function test_collecting_an_already_barcoded_sample_keeps_the_label(): void
    {
        $report = $this->report();

        Livewire::actingAs($this->lab)->test(LabOrders::class)
            ->call('collectSample', $report->id)
            ->assertHasNoErrors();

        $barcode = $report->fresh()->barcode;

        Livewire::actingAs($this->lab)->test(LabOrders::class)
            ->call('collectSample', $report->id)
            ->assertHasNoErrors();

        $this->assertSame($barcode, $report->fresh()->barcode);
    }

    public function test_a_result_file_is_stored_on_the_public_disk(): void
    {
        Storage::fake('public');

        $report = $this->report();

        Livewire::actingAs($this->lab)->test(LabOrders::class)
            ->call('openReport', $report->id)
            ->set('reportResult', 'See attached report.')
            ->set('resultFile', UploadedFile::fake()->create('result.pdf', 120, 'application/pdf'))
            ->call('saveResult')
            ->assertHasNoErrors();

        $report->refresh();

        $this->assertNotNull($report->file_path);
        $this->assertSame('reported', $report->status);
        Storage::disk('public')->assertExists($report->file_path);
    }

    public function test_a_critical_reported_result_alerts_the_doctors(): void
    {
        $report = $this->report();

        Livewire::actingAs($this->lab)->test(LabOrders::class)
            ->call('openReport', $report->id)
            ->set('reportResult', 'Troponin 9.8 ng/mL')
            ->set('isCritical', true)
            ->set('criticalNote', 'Suspected myocardial infarction')
            ->call('saveResult')
            ->assertHasNoErrors();

        Notification::assertSentTo($this->doctorUser, DoctorAlertRaised::class);

        $alert = DoctorAlert::firstOrFail();

        $this->assertSame('lab', $alert->category);
        $this->assertSame($this->patient->id, $alert->patient_id);
        $this->assertSame($this->lab->id, $alert->raised_by);
        $this->assertSame($this->tenant->id, $alert->tenant_id);
        $this->assertTrue((bool) $alert->is_urgent);

        $report->refresh();
        $this->assertTrue((bool) $report->is_critical);
        $this->assertSame('reported', $report->status);
    }

    public function test_the_barcode_slip_prints_and_is_module_gated(): void
    {
        $report = $this->report([
            'barcode' => 'LAB-'.$this->tenant->id.'-20261010-0001',
            'sample_collected_at' => now(),
        ]);

        $this->actingAs($this->lab)
            ->get(route('admin_lab_barcode_print', ['report' => $report->id]))
            ->assertOk()
            ->assertSee('LAB-'.$this->tenant->id.'-20261010-0001')
            ->assertSee($this->tenant->name);

        $blank = $this->report();

        $this->actingAs($this->lab)
            ->get(route('admin_lab_barcode_print', ['report' => $blank->id]))
            ->assertNotFound();

        $this->actingAs($this->reception)
            ->get(route('admin_lab_barcode_print', ['report' => $report->id]))
            ->assertForbidden();
    }

    public function test_the_api_issues_an_idempotent_sample_barcode(): void
    {
        $report = $this->report();

        $this->actingAs($this->lab, 'sanctum')
            ->postJson('/api/v1/admin/lab/'.$report->id.'/barcode')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'data' => ['barcode', 'sample_collected_at']]);

        $barcode = $report->fresh()->barcode;
        $this->assertNotNull($barcode);

        $this->actingAs($this->lab, 'sanctum')
            ->postJson('/api/v1/admin/lab/'.$report->id.'/barcode')
            ->assertOk()
            ->assertJsonPath('data.barcode', $barcode);

        $this->actingAs($this->lab, 'sanctum')
            ->postJson('/api/v1/admin/lab/999999/barcode')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_the_api_escalates_a_critical_result_and_rejects_bad_input(): void
    {
        $report = $this->report();

        $this->actingAs($this->lab, 'sanctum')
            ->postJson('/api/v1/admin/lab/'.$report->id.'/critical', [
                'is_critical' => true,
                'critical_note' => 'Potassium 7.2 mmol/L',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_critical', true);

        Notification::assertSentTo($this->doctorUser, DoctorAlertRaised::class);

        $alert = DoctorAlert::firstOrFail();
        $this->assertSame('lab', $alert->category);
        $this->assertSame($this->patient->id, $alert->patient_id);

        $this->actingAs($this->lab, 'sanctum')
            ->postJson('/api/v1/admin/lab/'.$report->id.'/critical', [
                'is_critical' => 'not-a-boolean',
                'critical_note' => str_repeat('x', 1200),
            ])
            ->assertStatus(422);
    }

    public function test_the_lab_queue_page_still_renders_for_the_lab_user(): void
    {
        $this->report();

        $this->actingAs($this->lab)->get('/admin/lab')->assertOk();

        Livewire::actingAs($this->lab)->test(LabOrders::class)
            ->assertSee('Lab Orders')
            ->assertSee($this->patient->name);
    }
}
