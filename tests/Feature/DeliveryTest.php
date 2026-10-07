<?php

namespace Tests\Feature;

use App\Models\DeliveryOrder;
use App\Models\patient;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 9 acceptance: only facilities that opted in (super admin
 * tenants.deliveries_enabled) get the delivery desk, and orders ride the
 * pending -> assigned -> out_for_delivery -> delivered rail (PLAN.md 12).
 */
class DeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Deliveries Hospital', 'slug' => 'deliveries-hospital',
            'mode' => 'hospital', 'status' => 'active', 'geo_radius_meters' => 200,
            'deliveries_enabled' => true,
        ]);
        $this->tenant->syncRequiredRoles([
            'admin', 'moderator', 'doctor', 'pharmacist',
            'storekeeper', 'receptionist',
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

    protected function patient()
    {
        return patient::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Rukhsar',
            'phone' => '0300-0000001',
            'address' => 'House 12, Garden Town',
        ]);
    }

    public function test_facilities_without_deliveries_see_no_panel_at_all(): void
    {
        $this->tenant->update(['deliveries_enabled' => false]);

        $storekeeper = $this->staff('storekeeper');
        $this->actingAs($storekeeper);

        $this->get(route('admin_deliveries'))->assertNotFound();

        $labels = collect(hms_sidebar_tree())->pluck('label');
        $this->assertFalse($labels->contains('Home Deliveries'));
    }

    public function test_enabled_facility_shows_the_delivery_panel(): void
    {
        $storekeeper = $this->staff('storekeeper');
        $this->actingAs($storekeeper);

        $this->get(route('admin_deliveries'))->assertOk();

        $labels = collect(hms_sidebar_tree())->pluck('label');
        $this->assertTrue($labels->contains('Home Deliveries'));
    }

    public function test_a_staff_member_can_raise_a_delivery_order(): void
    {
        $patient = $this->patient();
        $receptionist = $this->staff('receptionist');

        Livewire::actingAs($receptionist)
            ->test(\App\Http\Livewire\Admins\Deliveries::class)
            ->set('patientId', $patient->id)
            ->set('orderType', 'medicine')
            ->set('address', 'House 12, Garden Town')
            ->set('phone', '0300-1234567')
            ->set('deliveryFee', '50')
            ->call('createOrder')
            ->assertHasNoErrors();

        $order = DeliveryOrder::where('patient_id', $patient->id)->first();

        $this->assertNotNull($order);
        $this->assertSame('pending', $order->status);
        $this->assertSame('50.00', (string) $order->delivery_fee);
        $this->assertSame($receptionist->id, $order->created_by);
    }

    public function test_the_full_lifecycle_from_assign_to_delivered(): void
    {
        $by = $this->staff('receptionist');
        $this->actingAs($by);

        $order = app(DeliveryService::class)->create([
            'patient_id' => $this->patient()->id,
            'order_type' => 'lab',
            'address' => 'House 12, Garden Town',
            'phone' => '0300-1234567',
        ], $by);

        $storekeeper = $this->staff('storekeeper');

        Livewire::actingAs($storekeeper)
            ->test(\App\Http\Livewire\Admins\Deliveries::class)
            ->set('riderName', 'Ali Raza')
            ->set('vehicle', 'CD-70')
            ->call('assignRider', $order->id)
            ->call('markOutForDelivery', $order->id)
            ->call('complete', $order->id)
            ->assertHasNoErrors();

        $order->refresh();

        $this->assertSame(DeliveryOrder::DELIVERED, $order->status);
        $this->assertSame('Ali Raza', $order->rider_name);
        $this->assertNotNull($order->dispatched_at);
        $this->assertNotNull($order->delivered_at);
    }

    public function test_orders_cannot_move_backwards_on_the_rail(): void
    {
        $by = $this->staff('receptionist');
        $this->actingAs($by);

        $order = app(DeliveryService::class)->create([
            'patient_id' => $this->patient()->id,
            'order_type' => 'general',
            'address' => 'House 12, Garden Town',
            'phone' => '0300-1234567',
        ], $by);

        app(DeliveryService::class)->advance($order, DeliveryOrder::ASSIGNED);

        $this->expectException(\RuntimeException::class);

        app(DeliveryService::class)->advance($order, DeliveryOrder::PENDING);
    }

    public function test_only_delivery_managers_can_dispatch_orders(): void
    {
        $by = $this->staff('receptionist');
        $this->actingAs($by);

        $order = app(DeliveryService::class)->create([
            'patient_id' => $this->patient()->id,
            'order_type' => 'general',
            'address' => 'House 12, Garden Town',
            'phone' => '0300-1234567',
        ], $by);

        $doctor = $this->staff('doctor');
        $this->actingAs($doctor);

        $this->get(route('admin_deliveries'))->assertForbidden();
    }

    public function test_the_deliveries_api_lists_and_creates_orders(): void
    {
        $patient = $this->patient();

        $by = $this->staff('receptionist');
        $this->actingAs($by);

        app(DeliveryService::class)->create([
            'patient_id' => $patient->id,
            'order_type' => 'medicine',
            'address' => 'House 12, Garden Town',
            'phone' => '0300-1234567',
        ], $by);

        $this->actingAs($this->staff('storekeeper'), 'sanctum');

        $this->getJson(route('api.v1.admin.deliveries'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.order_type', 'medicine')
            ->assertJsonPath('data.0.status', 'pending');

        $this->postJson(route('api.v1.admin.deliveries.create'), [
            'patient_id' => $patient->id,
            'order_type' => 'general',
            'address' => 'Main Bazaar, Block B',
            'phone' => '0301-7654321',
            'delivery_fee' => 100,
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_the_deliveries_api_is_hidden_when_disabled(): void
    {
        $this->tenant->update(['deliveries_enabled' => false]);

        $this->actingAs($this->staff('storekeeper'), 'sanctum');

        $this->getJson(route('api.v1.admin.deliveries'))->assertNotFound();
    }
}