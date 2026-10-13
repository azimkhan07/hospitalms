<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\LabOrders;
use App\Http\Livewire\Admins\Prescriptions;
use App\Models\InvestigationReport;
use App\Models\InvestigationTest;
use App\Models\patient;
use App\Models\Prescription;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\LabReportReady;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Small clinical workflow gaps closed in the polish pass: e-prescription
 * quantity auto-calculation, the "report ready" doctor notification and the
 * guarantee that a doctor can never touch the billing ledger.
 */
class ClinicalQuickWinsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $doctor;

    protected User $lab;

    protected patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Quick Wins Hospital', 'slug' => 'quick-wins-hospital',
            'mode' => 'hospital', 'status' => 'active', 'geo_radius_meters' => 200,
        ]);
        $this->tenant->syncRequiredRoles([
            'admin', 'moderator', 'receptionist', 'doctor', 'laboratorist', 'accountant',
        ]);

        $this->doctor = $this->staff('doctor');
        $this->lab = $this->staff('laboratorist');

        $this->patient = new patient;
        $this->patient->tenant_id = $this->tenant->id;
        $this->patient->fill(['name' => 'Rx Patient', 'phone' => '0300-1234567']);
        $this->patient->save();
    }

    protected function staff(string $slug): User
    {
        return User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('slug', $slug)->value('id'),
            'is_active' => true,
        ]);
    }

    public function test_the_quantity_helper_reads_clinical_shorthand(): void
    {
        $this->assertSame(10, hms_rx_quantity('1 tab', 'BD', '5 days'));
        $this->assertSame(42, hms_rx_quantity('2 capsules', 'TDS', '7 days'));
        $this->assertSame(3, hms_rx_quantity('1 tab', 'once daily', '3 days'));
        $this->assertSame(4, hms_rx_quantity('1 cap', 'q6h', '1 day'));

        // Ambiguous or unsafe input stays blank instead of guessing.
        $this->assertNull(hms_rx_quantity('500 mg', 'BD', '5 days'));
        $this->assertNull(hms_rx_quantity('1 tab', 'as needed', '5 days'));
        $this->assertNull(hms_rx_quantity('1 tab', 'BD', 'until better'));
    }

    public function test_issuing_a_prescription_auto_fills_the_quantity(): void
    {
        Livewire::actingAs($this->doctor)->test(Prescriptions::class)
            ->set('patientId', $this->patient->id)
            ->set('items', [[
                'medicine' => 'Paracetamol',
                'dosage' => '1 tab',
                'frequency' => 'BD',
                'duration' => '5 days',
                'quantity' => '',
                'note' => '',
            ]])
            ->call('save')
            ->assertHasNoErrors();

        $item = Prescription::firstOrFail()->items()->firstOrFail();

        $this->assertSame(10, (int) $item->quantity);
    }

    public function test_a_typed_quantity_is_never_overwritten(): void
    {
        Livewire::actingAs($this->doctor)->test(Prescriptions::class)
            ->set('patientId', $this->patient->id)
            ->set('items', [[
                'medicine' => 'Amoxicillin',
                'dosage' => '1 cap',
                'frequency' => 'TDS',
                'duration' => '5 days',
                'quantity' => '20',
                'note' => '',
            ]])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(20, (int) Prescription::firstOrFail()->items()->first()->quantity);
    }

    public function test_reporting_a_result_notifies_the_ordering_doctor(): void
    {
        $test = InvestigationTest::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Liver Function Test',
            'code' => 'LFT',
            'base_rate' => 800,
            'calc_type' => 'flat',
            'is_active' => true,
        ]);

        $report = InvestigationReport::create([
            'tenant_id' => $this->tenant->id,
            'investigation_test_id' => $test->id,
            'patient_id' => $this->patient->id,
            'ordered_by' => $this->doctor->id,
            'status' => 'pending',
        ]);

        Livewire::actingAs($this->lab)->test(LabOrders::class)
            ->call('openReport', $report->id)
            ->set('reportResult', 'ALT 40 U/L, AST 38 U/L')
            ->call('saveResult')
            ->assertHasNoErrors();

        Notification::assertSentTo($this->doctor, LabReportReady::class);

        $this->assertSame('reported', $report->fresh()->status);
    }

    public function test_a_doctor_cannot_reach_the_billing_ledger(): void
    {
        $this->actingAs($this->doctor)->get(route('patient_bills'))->assertForbidden();
        $this->actingAs($this->doctor)->get(route('admin_accounting'))->assertForbidden();
    }
}
