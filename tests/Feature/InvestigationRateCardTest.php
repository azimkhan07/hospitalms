<?php

namespace Tests\Feature;

use App\Models\beds;
use App\Models\beds as Bed;
use App\Models\department as Department;
use App\Models\Concerns\BelongsToTenant;
use App\Models\InvestigationReport;
use App\Models\InvestigationTest;
use App\Models\Machine;
use App\Models\Role;
use App\Models\rooms;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvestigationCharge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The rate card is the thing the user asked to be configured and calculated
 * rather than typed, so these tests pin the arithmetic and the visibility of it.
 */
class InvestigationRateCardTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Diagnostics Hospital', 'slug' => 'diagnostics-hospital',
            'mode' => 'hospital', 'status' => 'active', 'geo_radius_meters' => 200,
        ]);
        $this->tenant->syncRequiredRoles(['admin', 'moderator', 'doctor', 'nurse']);
    }

    protected function staff(string $slug): User
    {
        return User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('slug', $slug)->value('id'),
            'is_active' => true,
        ]);
    }

    protected function machine(array $attrs = []): Machine
    {
        return Machine::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'MRI 1.5T',
            'status' => 'working',
            'rate' => 1200,
        ], $attrs));
    }

    protected function test(array $attrs = []): InvestigationTest
    {
        return InvestigationTest::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Brain MRI',
            'base_rate' => 1500,
            'per_unit_rate' => 300,
            'calc_type' => 'per_unit',
            'urgent_factor' => 1.5,
            'max_units' => 4,
            'is_active' => true,
        ], $attrs));
    }

    // --- the arithmetic ----------------------------------------------------

    public function test_a_flat_test_costs_its_base_rate(): void
    {
        $calc = InvestigationCharge::for($this->test(['calc_type' => 'flat', 'base_rate' => 450]), ['units' => 3]);

        $this->assertSame(450.0, $calc->total());
        $this->assertStringContainsString('450.00', $calc->formula());
    }

    public function test_a_per_unit_test_adds_the_rate_for_each_unit(): void
    {
        $calc = InvestigationCharge::for($this->test(['base_rate' => 500, 'per_unit_rate' => 250]), ['units' => 3]);

        // 500 + 250 x 3
        $this->assertSame(1250.0, $calc->total());
        $this->assertStringContainsString('500.00 + 250.00 x 3', $calc->formula());
    }

    public function test_the_machine_owns_the_price_when_the_test_defers_to_it(): void
    {
        $machine = $this->machine(['rate' => 2000]);
        $test = $this->test(['calc_type' => 'machine_rate', 'machine_id' => $machine->id, 'base_rate' => 999]);

        $calc = InvestigationCharge::for($test, ['units' => 2]);

        $this->assertSame(4000.0, $calc->total());
        $this->assertStringContainsString('machine rate', $calc->formula());
    }

    public function test_a_machine_rate_test_falls_back_to_its_base_rate(): void
    {
        // Charging nothing because nobody filled the machine's rate would be a
        // silent free investigation.
        $test = $this->test(['calc_type' => 'machine_rate', 'machine_id' => null, 'base_rate' => 800]);

        $calc = InvestigationCharge::for($test, ['units' => 2]);

        $this->assertSame(1600.0, $calc->total());
        $this->assertStringContainsString('no machine rate', $calc->formula());
    }

    public function test_an_urgent_report_is_multiplied_and_the_multiplier_is_shown(): void
    {
        $calc = InvestigationCharge::for($this->test(['base_rate' => 1000, 'calc_type' => 'flat']), [
            'units' => 1,
            'is_urgent' => true,
        ]);

        $this->assertSame(1500.0, $calc->total());
        $this->assertStringContainsString('urgent x 1.5', $calc->formula());
    }

    public function test_discount_and_tax_are_applied_in_that_order(): void
    {
        $calc = InvestigationCharge::for($this->test(['calc_type' => 'flat', 'base_rate' => 1000]), [
            'is_urgent' => true,   // 1500
            'discount' => 200,     // 1300
            'tax_percent' => 10,   // 1430
        ]);

        $this->assertSame(1430.0, $calc->total());
        $this->assertStringContainsString('discount -200.00', $calc->formula());
        $this->assertStringContainsString('tax +10%', $calc->formula());
    }

    public function test_a_discount_larger_than_the_charge_never_goes_negative(): void
    {
        $calc = InvestigationCharge::for($this->test(['calc_type' => 'flat', 'base_rate' => 100]), [
            'discount' => 500,
        ]);

        $this->assertSame(0.0, $calc->total());
    }

    public function test_units_are_never_taken_below_one(): void
    {
        $calc = InvestigationCharge::for($this->test(['calc_type' => 'flat', 'base_rate' => 100]), ['units' => 0]);

        $this->assertSame(100.0, $calc->total());
    }

    // --- a report freezes its own arithmetic -------------------------------

    public function test_a_report_stores_the_charge_and_the_formula_that_gave_it(): void
    {
        $test = $this->test(['base_rate' => 500, 'per_unit_rate' => 250]);

        $report = InvestigationReport::create([
            'tenant_id' => $this->tenant->id,
            'investigation_test_id' => $test->id,
            'units' => 2,
            'is_urgent' => false,
            'discount' => 0,
            'tax_percent' => 0,
        ]);

        $report->recalculate();

        $this->assertSame('1000.00', $report->fresh()->charge);
        $this->assertStringContainsString('500.00 + 250.00 x 2', $report->fresh()->formula);
    }

    public function test_an_old_report_keeps_its_number_after_the_rate_card_is_repriced(): void
    {
        $test = $this->test(['calc_type' => 'flat', 'base_rate' => 1000]);

        $report = InvestigationReport::create([
            'tenant_id' => $this->tenant->id,
            'investigation_test_id' => $test->id,
            'units' => 1,
        ]);
        $report->recalculate();

        $test->update(['base_rate' => 9999]);

        $this->assertSame('1000.00', $report->fresh()->charge);
    }

    // --- who may do what ---------------------------------------------------

    public function test_the_dean_manages_machines_and_prices_but_the_admin_only_reads(): void
    {
        $this->actingAs($this->staff('moderator'));
        Livewire::test(\App\Http\Livewire\Admins\Machines::class)
            ->assertOk()
            ->call('createMachine')
            ->set('name', 'ICU Ventilator')
            ->set('rate', '50')
            ->call('saveMachine')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('machines', ['name' => 'ICU Ventilator', 'tenant_id' => $this->tenant->id]);

        $this->actingAs($this->staff('admin'));
        Livewire::test(\App\Http\Livewire\Admins\Machines::class)
            ->assertOk()
            ->call('createMachine')
            ->assertForbidden();
    }

    public function test_a_doctor_may_not_see_the_rate_card(): void
    {
        $this->actingAs($this->staff('doctor'));

        Livewire::test(\App\Http\Livewire\Admins\Investigations::class)->assertForbidden();
    }

    public function test_a_used_test_is_retired_rather_than_deleted(): void
    {
        $this->actingAs($this->staff('moderator'));
        $test = $this->test();

        InvestigationReport::create([
            'tenant_id' => $this->tenant->id,
            'investigation_test_id' => $test->id,
            'units' => 1,
        ]);

        Livewire::test(\App\Http\Livewire\Admins\Investigations::class)
            ->call('deleteTest', $test->id);

// Deleting would leave an old report with no name on it, so the test is
        // retired instead: it leaves the card but stays readable on history.
        $this->assertDatabaseHas('investigation_tests', [
            'id' => $test->id, 'is_active' => false, 'deleted_at' => null,
        ]);
    }

    public function test_a_machine_with_investigations_cannot_be_deleted(): void
    {
        $this->actingAs($this->staff('moderator'));
        $machine = $this->machine();

        InvestigationReport::create([
            'tenant_id' => $this->tenant->id,
            'investigation_test_id' => $this->test(['machine_id' => $machine->id])->id,
            'machine_id' => $machine->id,
            'units' => 1,
        ]);

        Livewire::test(\App\Http\Livewire\Admins\Machines::class)
            ->call('deleteMachine', $machine->id);

        $this->assertDatabaseHas('machines', ['id' => $machine->id, 'deleted_at' => null]);
    }

    // --- ICU scope ---------------------------------------------------------

public function test_a_nurse_can_record_a_machine_in_the_ward(): void
    {
        $bed = $this->bed();

        $this->actingAs($this->staff('nurse'));

        // Go through the click the nurse actually makes: pick the bed on the
        // map, then record. The bed has to arrive on its own -- reading it off a
        // hidden input silently produced a machine with no bed.
        Livewire::test(\App\Http\Livewire\Admins\BedReports::class)
            ->call('pickBed', (string) $bed->id)
            ->set('showMachineForm', true)
            ->set('machineName', 'Portable Ventilator')
            ->set('machineModality', 'Ventilator')
            ->call('createMachine')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('machines', [
            'name' => 'Portable Ventilator',
            'bed_id' => $bed->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_a_doctor_sees_everything_recorded_against_a_bed(): void
    {
        $bed = $this->bed();
        $test = $this->test(['calc_type' => 'flat', 'base_rate' => 700]);

        $report = InvestigationReport::create([
            'tenant_id' => $this->tenant->id,
            'investigation_test_id' => $test->id,
            'bed_id' => $bed->id,
            'units' => 1,
            'findings' => 'No midline shift.',
        ]);
        $report->recalculate();

        $this->actingAs($this->staff('doctor'));

        Livewire::test(\App\Http\Livewire\Admins\BedReports::class)
            ->set('bedId', (string) $bed->id)
            ->assertViewHas('bed')
            ->assertViewHas('reports', fn ($rows) => $rows->count() === 1)
            ->assertViewHas('total', 700.0);
    }

    public function test_a_doctor_cannot_reach_a_bed_report_for_a_facility_that_is_not_theirs(): void
    {
        $other = Tenant::create([
            'name' => 'Other Hospital', 'slug' => 'other-hospital',
            'mode' => 'hospital', 'status' => 'active', 'geo_radius_meters' => 200,
        ]);

$otherBed = Bed::create([
            'tenant_id' => $other->id,
            'room_id' => rooms::create([
                'tenant_id' => $other->id, 'name' => 'ICU-X', 'status' => 'available',
                'department_id' => $this->department($other)->id,
            ])->id,
            'bed_number' => 'X-99',
            'status' => 'available',
        ]);

        $this->actingAs($this->staff('doctor'));

        $rows = Livewire::test(\App\Http\Livewire\Admins\BedReports::class)
            ->set('bedId', (string) $otherBed->id)
            ->viewData('reports');

        $this->assertCount(0, $rows);
    }

    // --- tenant isolation --------------------------------------------------

    public function test_one_facility_cannot_see_another_facilities_machines(): void
    {
        $mine = $this->machine(['name' => 'Our MRI']);
        Machine::create([
            'tenant_id' => $this->otherTenant()->id, 'name' => 'Their MRI', 'status' => 'working', 'rate' => 10,
        ]);

        $this->actingAs($this->staff('admin'));

Livewire::test(\App\Http\Livewire\Admins\Machines::class)
            ->assertViewHas('machines', function ($rows) use ($mine) {
                $this->assertEqualsCanonicalizing([$mine->id], array_values($rows->pluck('id')->all()));

                return true;
            });
    }

    protected function otherTenant(): Tenant
    {
        return Tenant::create([
            'name' => 'Other Diagnostics', 'slug' => 'other-diagnostics',
            'mode' => 'hospital', 'status' => 'active', 'geo_radius_meters' => 200,
        ]);
    }

    protected function block(?Tenant $tenant = null): \App\Models\block
    {
        $tenant ??= $this->tenant;

        return \App\Models\block::create([
            'tenant_id' => $tenant->id,
            'blockname' => 'ICU Block',
            'blockcode' => 9000,
        ]);
    }

    protected function department(?Tenant $tenant = null): Department
    {
        $tenant ??= $this->tenant;

        return Department::create([
            'tenant_id' => $tenant->id,
            'name' => 'ICU',
            'description' => 'Intensive care',
            'photo_path' => '',
            'hod_id' => $this->staff('doctor')->id,
            'block_id' => $this->block($tenant)->id,
        ]);
    }

    protected function room(): rooms
    {
        return rooms::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'ICU-1',
            'status' => 'active',
        ]);
    }

protected function bed(): Bed
    {
        return Bed::create([
            'tenant_id' => $this->tenant->id,
            'room_id' => rooms::create([
                'tenant_id' => $this->tenant->id, 'name' => 'ICU-1', 'status' => 'available',
                'department_id' => $this->department()->id,
            ])->id,
            'bed_number' => 'A-01',
            'status' => 'alloted',
        ]);
    }
}