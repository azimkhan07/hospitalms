<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\Prescriptions;
use App\Models\medicine;
use App\Models\patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Pharmacy as PharmacyService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Med quantity auto-calc on dispense: the doctor records how many units a
 * prescription line needs and the pharmacist's dispense deducts exactly those
 * units from the earliest-expiry batch — or refuses when stock cannot cover
 * the line, writing nothing.
 */
class MedQuantityDispenseTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $doctor;

    protected User $pharmacist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Med Quantity Hospital', 'slug' => 'med-quantity-hospital',
            'mode' => 'hospital', 'status' => 'active', 'geo_radius_meters' => 200,
        ]);
        $this->tenant->syncRequiredRoles(['admin', 'doctor', 'pharmacist']);

        $this->doctor = $this->staff('doctor');
        $this->pharmacist = $this->staff('pharmacist');

        Auth::login($this->staff('admin'));
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
     * Rows are built outside a session (nobody is signed in as their owner),
     * so the facility is set directly — tenant_id stays off the fillable lists.
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
            'quantity' => '10',
            'batch_no' => 'B1',
            'expiry_date' => today()->addMonths(6),
            'stock' => 10,
            'reorder_level' => 5,
        ], $attrs));
    }

    protected function patient(string $name): patient
    {
        return $this->make(patient::class, [
            'name' => $name,
            'phone' => '0300-'.random_int(1000000, 9999999),
        ]);
    }

    protected function prescription(patient $patient, medicine $medicine, ?int $quantity): Prescription
    {
        $rx = $this->make(Prescription::class, [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $this->make(PrescriptionItem::class, [
            'prescription_id' => $rx->id,
            'medicine' => $medicine->name,
            'medicine_id' => $medicine->id,
            'dosage' => '1 tab',
            'frequency' => '3 x daily',
            'quantity' => $quantity,
        ]);

        return $rx;
    }

    public function test_the_prescription_form_persists_a_quantity_per_line(): void
    {
        $m = $this->medicine();
        $patient = $this->patient('Aisha');

        Livewire::actingAs($this->doctor)
            ->test(Prescriptions::class)
            ->set('patientId', $patient->id)
            ->set('items.0.medicine', $m->name)
            ->set('items.0.dosage', '1 tab')
            ->set('items.0.quantity', 6)
            ->call('save')
            ->assertHasNoErrors();

        $rx = Prescription::where('patient_id', $patient->id)->latest('id')->firstOrFail();

        $this->assertSame(6, (int) $rx->items()->value('quantity'));
        $this->assertNotNull($rx->items()->value('medicine_id'));
    }

    public function test_dispense_hands_over_the_units_the_doctor_asked_for(): void
    {
        $m = $this->medicine(['stock' => 10]);
        $rx = $this->prescription($this->patient('Bilal'), $m, 4);

        $this->actingAs($this->pharmacist);

        app(PharmacyService::class)->dispensePrescription($rx, $this->pharmacist);

        $item = $rx->fresh()->items->first();

        $this->assertSame(6, $m->fresh()->stock);
        $this->assertSame(4, (int) $item->dispensed_qty);
        $this->assertNotNull($item->dispensed_at);
        $this->assertNotNull($rx->fresh()->dispensed_at);
        $this->assertTrue($rx->fresh()->isFullyDispensed());
    }

    public function test_a_line_without_a_quantity_still_hands_over_one_unit(): void
    {
        $m = $this->medicine(['stock' => 10]);
        $rx = $this->prescription($this->patient('Celine'), $m, null);

        $this->actingAs($this->pharmacist);

        app(PharmacyService::class)->dispensePrescription($rx, $this->pharmacist);

        $item = $rx->fresh()->items->first();

        $this->assertSame(9, $m->fresh()->stock);
        $this->assertSame(1, (int) $item->dispensed_qty);
        $this->assertNotNull($rx->fresh()->dispensed_at);
    }

    public function test_insufficient_stock_blocks_the_whole_dispense_and_writes_nothing(): void
    {
        $m = $this->medicine(['stock' => 3]);
        $rx = $this->prescription($this->patient('Daniyal'), $m, 5);

        $this->actingAs($this->pharmacist);

        try {
            app(PharmacyService::class)->dispensePrescription($rx, $this->pharmacist);
            $this->fail('Expected a RuntimeException for an insufficient stock line.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('the prescription needs 5', $e->getMessage());
        }

        $this->assertDatabaseHas('medicines', ['id' => $m->id, 'stock' => 3]);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertNull($rx->fresh()->dispensed_at);
        $this->assertSame(0, (int) $rx->fresh()->items->first()->dispensed_qty);
    }

    public function test_the_dispense_api_mirrors_the_counter(): void
    {
        $m = $this->medicine(['stock' => 10]);
        $rx = $this->prescription($this->patient('Farah'), $m, 4);

        Sanctum::actingAs($this->pharmacist, ['*']);

        $this->postJson('/api/v1/admin/prescriptions/'.$rx->id.'/dispense')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Dispensed.')
            ->assertJsonPath('data.dispensed_items', 1);

        $this->assertSame(6, $m->fresh()->stock);
        $this->assertSame(4, (int) $rx->fresh()->items->first()->dispensed_qty);

        $low = $this->medicine(['name' => 'Low Stock Tab', 'code' => 'LOW-STOCK', 'stock' => 2]);
        $short = $this->prescription($this->patient('Gulan'), $low, 5);

        $this->postJson('/api/v1/admin/prescriptions/'.$short->id.'/dispense')
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(2, $low->fresh()->stock);
        $this->assertNull($short->fresh()->dispensed_at);
        $this->assertSame(0, (int) $short->fresh()->items->first()->dispensed_qty);

        Sanctum::actingAs($this->doctor, ['*']);

        $this->postJson('/api/v1/admin/prescriptions/'.$short->id.'/dispense')
            ->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->assertNull($short->fresh()->dispensed_at);
    }

    public function test_the_rx_api_lists_the_queue_with_quantities(): void
    {
        $m = $this->medicine(['stock' => 10]);
        $rx = $this->prescription($this->patient('Hina'), $m, 4);

        Sanctum::actingAs($this->pharmacist, ['*']);

        $this->getJson('/api/v1/admin/pharmacy/rx?filter=queue')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.list')
            ->assertJsonPath('data.list.0.id', $rx->id)
            ->assertJsonPath('data.list.0.patient', 'Hina')
            ->assertJsonPath('data.list.0.item_count', 1)
            ->assertJsonPath('data.list.0.items.0.quantity', 4)
            ->assertJsonPath('data.pagination.total', 1);

        app(PharmacyService::class)->dispensePrescription($rx, $this->pharmacist);

        $this->getJson('/api/v1/admin/pharmacy/rx?filter=dispensed')
            ->assertOk()
            ->assertJsonCount(1, 'data.list')
            ->assertJsonPath('data.list.0.items.0.dispensed_qty', 4);

        $this->getJson('/api/v1/admin/pharmacy/rx?filter=queue')
            ->assertOk()
            ->assertJsonCount(0, 'data.list');
    }
}
