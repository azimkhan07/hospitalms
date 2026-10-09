<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\Accounts;
use App\Http\Livewire\Admins\Bills;
use App\Http\Livewire\Admins\DayBook;
use App\Models\BillItem;
use App\Models\bill;
use App\Models\block;
use App\Models\department;
use App\Models\patient;
use App\Models\payment;
use App\Models\Role;
use App\Models\rooms;
use App\Models\stay;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Billing milestone: itemised bills with discount/advance, the IPD
 * discharge finalisation and the finance day book (PLAN billing).
 */
class BillingLineItemsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $admin;

    protected User $accountant;

    protected User $reception;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Billing Hospital', 'slug' => 'billing-hospital',
            'mode' => 'hospital', 'status' => 'active', 'geo_radius_meters' => 200,
        ]);
        $this->tenant->syncRequiredRoles([
            'admin', 'moderator', 'receptionist', 'doctor', 'nurse', 'accountant',
        ]);

        $this->admin = $this->staff('admin');
        $this->accountant = $this->staff('accountant');
        $this->reception = $this->staff('receptionist');

        Auth::login($this->admin);
    }

    protected function staff(string $slug): User
    {
        return User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('slug', $slug)->value('id'),
            'is_active' => true,
        ]);
    }

    protected function patient(string $name): patient
    {
        Auth::login($this->admin);

        return patient::create([
            'name' => $name,
            'phone' => '0300-'.random_int(1000000, 9999999),
        ]);
    }

    protected function openBill(patient $patient, float $amount = 0): bill
    {
        Auth::login($this->admin);

        return bill::create([
            'patients_id' => $patient->id,
            'status' => 'unpaid',
            'amount' => $amount,
            'tax' => 0,
            'discount' => 0,
            'invoice_no' => 'INV-'.random_int(1000, 9999),
        ]);
    }

    protected function paidBill(patient $patient, float $amount): bill
    {
        $bill = $this->openBill($patient, $amount);

        payment::create([
            'patient_id' => $patient->id,
            'bill_id' => $bill->id,
            'amount' => $amount,
            'status' => 'paid',
            'mode' => 'cash',
        ]);

        return $bill;
    }

    protected function ipdRoom(): rooms
    {
        $block = block::create(['blockname' => 'Main', 'blockcode' => 1000]);

        $department = department::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Medicine',
            'description' => 'General medicine ward.',
            'photo_path' => '',
            'hod_id' => $this->admin->id,
            'block_id' => $block->id,
        ]);

        return rooms::create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $department->id,
            'name' => 'Room 4',
            'type' => 'private',
            'capacity' => 1,
            'daily_rate' => 2500,
            'status' => 'available',
        ]);
    }

    public function test_bills_page_is_forbidden_without_the_bills_module(): void
    {
        Livewire::actingAs($this->reception)
            ->test(Bills::class)
            ->assertForbidden();
    }

    public function test_accountant_adds_line_items_and_the_bill_total_is_recalculated(): void
    {
        $bill = $this->openBill($this->patient('Nadia'), 100.00);

        Livewire::actingAs($this->accountant)
            ->test(Bills::class)
            ->call('openItems', $bill->id)
            ->set('item_description', 'CBC panel')
            ->set('item_category', 'lab')
            ->set('item_qty', 2)
            ->set('item_rate', 150)
            ->call('addItem')
            ->assertHasNoErrors()
            ->assertSee('CBC panel');

        $item = BillItem::where('bill_id', $bill->id)->firstOrFail();

        $this->assertSame('300.00', (string) $item->amount);
        $this->assertSame('lab', $item->category);
        $this->assertSame($this->accountant->id, (int) $item->created_by);
        $this->assertSame(300.0, (float) $bill->fresh()->amount);
        $this->assertSame(300.0, $bill->fresh()->amountDue());
    }

    public function test_discount_and_advance_are_persisted_and_reduce_the_amount_due(): void
    {
        $bill = $this->openBill($this->patient('Farah'), 500.00);

        Livewire::actingAs($this->accountant)
            ->test(Bills::class)
            ->call('edit', $bill->id)
            ->set('discount_amount', 50)
            ->set('advance_used', 100)
            ->set('remarks', 'Corporate cover')
            ->call('add_bill')
            ->assertHasNoErrors();

        $bill->refresh();

        $this->assertSame(50.0, (float) $bill->discount_amount);
        $this->assertSame(100.0, (float) $bill->advance_used);
        $this->assertSame('Corporate cover', $bill->remarks);
        // The typed 500 is the billing base; discount_amount is applied on top.
        $this->assertSame(500.0, (float) $bill->amount);
        // net (500 - 50 discount) - advance (100) - paid (0)
        $this->assertSame(350.0, $bill->amountDue());

        Auth::login($this->accountant);
        payment::create([
            'patient_id' => $bill->patients_id,
            'bill_id' => $bill->id,
            'amount' => 150,
            'status' => 'paid',
            'mode' => 'cash',
        ]);

        // max(0, net - advance - paid)
        $this->assertSame(200.0, $bill->fresh()->amountDue());
    }

    public function test_finalise_stay_charges_the_accommodation_nights(): void
    {
        $patient = $this->patient('Iqra');
        $bill = $this->openBill($patient, 0.00);

        Auth::login($this->admin);
        $room = $this->ipdRoom();
        $stay = stay::create([
            'patient_id' => $patient->id,
            'room_id' => $room->id,
            'start_time' => now()->subDays(2)->subMinutes(30)->timestamp,
            'status' => 'active',
        ]);

        Livewire::actingAs($this->accountant)
            ->test(Bills::class)
            ->call('finaliseStay', $bill->id)
            ->assertHasNoErrors();

        $item = BillItem::where('bill_id', $bill->id)->firstOrFail();

        $this->assertSame('room', $item->category);
        $this->assertStringContainsString('night(s)', $item->description);
        $this->assertSame(3.0, (float) $item->qty);
        $this->assertSame(2500.0, (float) $item->rate);
        $this->assertSame(7500.0, (float) $item->amount);
        $this->assertSame($stay->id, (int) $bill->fresh()->stay_id);
        $this->assertSame(7500.0, (float) $bill->fresh()->amount);
        $this->assertNotNull($bill->fresh()->remarks);
    }

    public function test_finalise_stay_without_a_stay_flashes_an_error_instead_of_crashing(): void
    {
        $bill = $this->openBill($this->patient('Sara'), 0.00);

        Livewire::actingAs($this->accountant)
            ->test(Bills::class)
            ->call('finaliseStay', $bill->id)
            ->assertHasNoErrors()
            ->assertSee('No IPD stay found');

        $this->assertNull($bill->fresh()->stay_id);
        $this->assertSame(0, BillItem::where('bill_id', $bill->id)->count());
    }

    public function test_billing_api_keeps_the_line_items_and_totals_in_sync(): void
    {
        $bill = $this->openBill($this->patient('Hina'), 0.00);

        Sanctum::actingAs($this->accountant, ['*']);

        $this->postJson('/api/v1/admin/bills/'.$bill->id.'/items', [
            'description' => 'Dressing', 'category' => 'procedure', 'qty' => 2, 'rate' => 120,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.totals.gross', 240)
            ->assertJsonPath('data.totals.net', 240)
            ->assertJsonPath('data.totals.due', 240);

        $this->getJson('/api/v1/admin/bills/'.$bill->id.'/items')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.totals.net', 240);

        $itemId = BillItem::where('bill_id', $bill->id)->value('id');

        $this->patchJson('/api/v1/admin/bills/'.$bill->id, [
            'discount_amount' => 40, 'advance_used' => 20, 'remarks' => 'Scheme cover',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.totals.net', 200)
            ->assertJsonPath('data.totals.due', 180);

        $this->deleteJson('/api/v1/admin/bills/'.$bill->id.'/items/'.$itemId)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.totals.net', 200)
            ->assertJsonPath('data.totals.due', 180);

        $this->assertSame(0, BillItem::count());

        // Without items the typed/total base is preserved (240) and the
        // discount stays a display-level deduction: net 200, due after advance 180.
        $bill->refresh();
        $this->assertSame(240.0, (float) $bill->amount);
        $this->assertSame(180.0, $bill->amountDue());
    }

    public function test_bills_api_is_forbidden_without_the_bills_module(): void
    {
        $bill = $this->openBill($this->patient('Rida'), 0.00);

        Sanctum::actingAs($this->reception, ['*']);

        $this->getJson('/api/v1/admin/bills/'.$bill->id.'/items')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_day_book_lists_the_paid_receipts_of_the_selected_date(): void
    {
        $patient = $this->patient('Zoya');
        $bill = $this->paidBill($patient, 400.00);

        Livewire::actingAs($this->accountant)
            ->test(DayBook::class)
            ->assertSet('date', now()->format('Y-m-d'))
            ->assertSee('Receipts')
            ->assertSee('Zoya')
            ->assertSee('400.00');

        Sanctum::actingAs($this->accountant, ['*']);

        $this->getJson('/api/v1/admin/day-book?date='.now()->format('Y-m-d'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.receipts', 400)
            ->assertJsonPath('data.net', 400)
            ->assertJsonCount(1, 'data.entries');
    }

    public function test_a_full_payment_flips_the_bill_to_paid(): void
    {
        $bill = $this->openBill($this->patient('Gul'), 0.00);

        Livewire::actingAs($this->accountant)
            ->test(Bills::class)
            ->call('openItems', $bill->id)
            ->set('item_description', 'Room rent')
            ->set('item_category', 'room')
            ->set('item_qty', 1)
            ->set('item_rate', 300)
            ->call('addItem')
            ->assertHasNoErrors();

        $this->assertSame(300.0, (float) $bill->fresh()->amount);

        Livewire::actingAs($this->accountant)
            ->test(Accounts::class)
            ->call('accept', $bill->id)
            ->set('receiveAmount', 300)
            ->call('recordPayment')
            ->assertHasNoErrors();

        $bill->refresh();

        $this->assertSame('paid', $bill->status);
        $this->assertNotNull($bill->paid_at);
        $this->assertSame(0.0, $bill->amountDue());
    }
}
