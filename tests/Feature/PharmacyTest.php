<?php

namespace Tests\Feature;

use App\Models\bill;
use App\Models\medicine;
use App\Models\patient;
use App\Models\payment;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Pharmacy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 7 acceptance: dispensing a prescription reduces stock, expiry is
 * visible, and payment-done posts to the patient bill (PLAN.md 10).
 */
class PharmacyTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Pharmacy Hospital', 'slug' => 'pharmacy-hospital',
            'mode' => 'hospital', 'status' => 'active', 'geo_radius_meters' => 200,
        ]);
        $this->tenant->syncRequiredRoles(['admin', 'moderator', 'doctor', 'nurse', 'pharmacist', 'storekeeper']);
    }

    protected function staff(string $slug): User
    {
        return User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('slug', $slug)->value('id'),
            'is_active' => true,
        ]);
    }

/**
     * Rows are built outside a session (no one logged in to stamp them), and
     * tenant_id stays off the fillable lists, so the facility is set directly.
     */
    protected function make(string $model, array $attributes)
    {
        $row = new $model;
        $row->tenant_id = $this->tenant->id;
        $row->fill($attributes);
        $row->save();

        return $row;
    }

    protected function medicine(array $attrs = []): medicine
    {
        return $this->make(medicine::class, array_merge([
            'name' => 'Panadol Extra',
            'generic' => 'Paracetamol + Caffeine',
            'code' => 'PND-EXTRA',
            'price' => 35,
            'mrp' => 40,
            'batch_no' => 'B1',
            'expiry_date' => today()->addMonths(6),
            'stock' => 0,
            'quantity' => 0,
            'reorder_level' => 5,
        ], $attrs));
    }

    protected function prescription(patient $patient, ?medicine $linked): Prescription
    {
        $rx = $this->make(Prescription::class, [
            'patient_id' => $patient->id,
            'doctor_id' => $this->staff('doctor')->id,
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $this->make(PrescriptionItem::class, [
            'prescription_id' => $rx->id,
            'medicine' => $linked?->name ?? 'A free text medicine',
            'medicine_id' => $linked?->id,
            'dosage' => '1 tab',
            'frequency' => '3 x daily',
        ]);

        return $rx;
    }

    public function test_stock_in_and_out_keeps_a_provable_ledger(): void
    {
        $this->actingAs($this->staff('storekeeper'));

        $m = $this->medicine();
        $service = app(Pharmacy::class);

$m = $service->purchase($m, 100, 'B1', 'GRN #1');
        $service->dispense($m, 7, $this->staff('pharmacist'), 'RX #1');

        $this->assertSame(93, $m->fresh()->stock);

        $movements = $m->fresh()->stockMovements;

        $this->assertCount(2, $movements);
        $this->assertSame(100, $movements->where('type', 'purchase')->sum('quantity'));
        $this->assertSame(-7, $movements->where('type', 'dispense')->sum('quantity'));
    }

    public function test_stock_cannot_go_below_zero(): void
    {
        $m = $this->medicine();

        $this->expectException(\RuntimeException::class);

        app(Pharmacy::class)->dispense($m, 1);
    }

    public function test_feefo_uses_the_earliest_expiry_batch_first(): void
    {
        $this->actingAs($this->staff('storekeeper'));

        // Two batches of the same medicine; the one expiring sooner costs more.
        $sooner = $this->medicine([
            'code' => 'PND-EXTRA', 'name' => 'Panadol Extra',
            'batch_no' => 'A1', 'expiry_date' => today()->addMonth(), 'price' => 40,
        ]);
        $later = $this->medicine([
            'code' => 'PND-EXTRA', 'name' => 'Panadol Extra',
            'batch_no' => 'A2', 'expiry_date' => today()->addYear(), 'price' => 35,
        ]);

        app(Pharmacy::class)->purchase($sooner, 10, 'A1');
        app(Pharmacy::class)->purchase($later, 10, 'A2');

        app(Pharmacy::class)->dispense($sooner, 8, $this->staff('pharmacist'), 'RX #2');

        // The 8 units must come off the batch expiring soonest.
        $this->assertSame(2, $sooner->fresh()->stock);
        $this->assertSame(10, $later->fresh()->stock);
    }

    public function test_dispensing_a_prescription_deducts_stock_and_stamps_it(): void
    {
        $m = $this->medicine();
        $patient = $this->make(patient::class, ['name' => 'Aisha']);
        $rx = $this->prescription($patient, $m);

        $this->actingAs($this->staff('pharmacist'));

        app(Pharmacy::class)->purchase($m, 50, 'B1');
        app(Pharmacy::class)->dispensePrescription($rx, $this->staff('pharmacist'));

        $this->assertSame(49, $m->fresh()->stock);
        $this->assertNotNull($rx->fresh()->dispensed_at);
        $this->assertSame(1, $rx->fresh()->items->first()->dispensed_qty);
    }

    public function test_an_out_of_stock_line_blocks_the_whole_dispense(): void
    {
        $m = $this->medicine();
        $patient = $this->make(patient::class, ['name' => 'Bilal']);
        $rx = $this->prescription($patient, $m);

        $this->actingAs($this->staff('pharmacist'));

        try {
            app(Pharmacy::class)->dispensePrescription($rx, $this->staff('pharmacist'));
            $this->fail('Expected a RuntimeException for an out-of-stock medicine.');
        } catch (\RuntimeException $e) {
            // expected
        }

        $this->assertNull($rx->fresh()->dispensed_at);
        $this->assertSame(0, $m->fresh()->stock);
    }

    public function test_the_dispense_screen_opens_with_fefo_availability(): void
    {
        $m = $this->medicine();
        $patient = $this->make(patient::class, ['name' => 'Celine']);
        $rx = $this->prescription($patient, $m);

        // The counter shows the batch only when there is stock behind it.
        app(Pharmacy::class)->purchase($m, 15, 'B1');

        $this->actingAs($this->staff('pharmacist'));

        Livewire::test(\App\Http\Livewire\Admins\Pharmacy::class)
            ->set('filter', 'queue')
            ->assertSee('Celine')
            ->call('open', $rx->id)
            ->assertSee('B1')
            ->assertSee('Panadol Extra');
    }

    public function test_pharmacist_can_dispense_from_the_screen(): void
    {
        $m = $this->medicine();
        $patient = $this->make(patient::class, ['name' => 'Daniyal']);
        $rx = $this->prescription($patient, $m);

        $this->actingAs($this->staff('pharmacist'));

        app(Pharmacy::class)->purchase($m, 20, 'B1');

        Livewire::test(\App\Http\Livewire\Admins\Pharmacy::class)
            ->call('open', $rx->id)
            ->call('dispense')
            ->assertHasNoErrors()
            ->assertSet('openId', null);

        $this->assertSame(19, $m->fresh()->stock);
        $this->assertNotNull($rx->fresh()->dispensed_at);
    }

    public function test_mark_paid_posts_to_the_patient_bill(): void
    {
        $m = $this->medicine(['mrp' => 40, 'price' => 35]);
        $patient = $this->make(patient::class, ['name' => 'Emaan']);
        $rx = $this->prescription($patient, $m);

        $this->actingAs($this->staff('pharmacist'));

        app(Pharmacy::class)->purchase($m, 20, 'B1');
        app(Pharmacy::class)->dispensePrescription($rx, $this->staff('pharmacist'));

        $bank = DB::table('bills')->count();
        $payments = DB::table('payments')->count();

        Livewire::test(\App\Http\Livewire\Admins\Pharmacy::class)
            ->call('open', $rx->id)
            ->call('markPaid', 'cash');

        $this->assertDatabaseCount('bills', $bank + 1);
        $this->assertDatabaseCount('payments', $payments + 1);

        $payment = payment::latest('id')->first();

        $this->assertSame((string) $patient->id, (string) $payment->patient_id);
        $this->assertSame('40.00', (string) $payment->amount);
        $this->assertSame('paid', $payment->status);
        $this->assertSame('paid', $rx->fresh()->payment_status);
    }

    public function test_payment_requires_every_line_dispensed(): void
    {
        $patient = $this->make(patient::class, ['name' => 'Farah']);
        $rx = $this->prescription($patient, null);

        $this->actingAs($this->staff('pharmacist'));

        Livewire::test(\App\Http\Livewire\Admins\Pharmacy::class)
            ->call('open', $rx->id)
            ->call('markPaid', 'cash')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('unpaid', $rx->fresh()->payment_status);
    }

    public function test_the_storekeeper_sees_the_master_and_can_stock_in(): void
    {
        $m = $this->medicine();

        $this->actingAs($this->staff('storekeeper'));

        Livewire::test(\App\Http\Livewire\Admins\Medicinestore::class)
            ->assertSee('Panadol Extra')
            ->set('stockInId', $m->id)
            ->set('showStockIn', true)
            ->set('inQty', 60)
            ->set('inNote', 'Delivery #12')
            ->call('recordStockIn')
            ->assertHasNoErrors();

        $this->assertSame(60, $m->fresh()->stock);
    }

    public function test_the_pharmacist_cannot_edit_the_master_or_post_stock_in(): void
    {
        $m = $this->medicine();

        $this->actingAs($this->staff('pharmacist'));

        Livewire::test(\App\Http\Livewire\Admins\Medicinestore::class)
            ->call('edit', $m->id)
            ->assertForbidden();

        Livewire::test(\App\Http\Livewire\Admins\Medicinestore::class)
            ->set('stockInId', $m->id)
            ->set('showStockIn', true)
            ->set('inQty', 5)
            ->call('recordStockIn')
            ->assertForbidden();

        $this->assertSame(0, $m->fresh()->stock);
    }
}