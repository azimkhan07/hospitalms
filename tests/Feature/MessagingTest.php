<?php

namespace Tests\Feature;

use App\Models\MessageLog;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Messaging\MessagingService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SMS + WhatsApp acceptance (PLAN.md 18d): the container resolves a transport
 * (log by default), every send is audited in message_logs, and the channels
 * fire when an appointment request lands from the website or mobile app.
 */
class MessagingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->tenant = Tenant::create([
            'name' => 'Message Hospital',
            'slug' => 'message-hospital',
            'mode' => 'hospital',
            'status' => 'active',
            'geo_radius_meters' => 200,
        ]);
        $this->tenant->syncRequiredRoles(['admin', 'doctor', 'receptionist']);
    }

    private function staff(string $slug): User
    {
        return User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('slug', $slug)->value('id'),
            'is_active' => true,
        ]);
    }

    public function test_sms_and_whatsapp_are_logged_by_the_default_transport(): void
    {
        $this->actingAs($this->staff('admin'));

        /** @var MessagingService $service */
        $service = app('App\Contracts\MessageSender');

        $service->sms('0300-1234567', 'Your OTP is 4812');
        $service->whatsapp('0300-1234567', 'Reminder: appointment tomorrow 9 AM');

        $this->assertDatabaseCount('message_logs', 2);

        $sms = MessageLog::where('channel', 'sms')->first();
        $wa = MessageLog::where('channel', 'whatsapp')->first();

        $this->assertSame('sent', $sms->status);
        $this->assertSame('0300-1234567', $wa->phone);
        $this->assertSame($this->tenant->id, $sms->tenant_id);
    }

    public function test_an_appointment_request_from_the_website_fires_both_channels(): void
    {
        $this->withoutMix();

        $doctor = \App\Models\doctor::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => \App\Models\employee::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'Dr. Akbar',
                'email' => 'akbar@example.com',
                'phone' => '0300-1111',
                'position' => 'doctor',
            ])->id,
        ]);

        \Livewire\Livewire::test(\App\Http\Livewire\Appointmentform::class)
            ->set('name', 'Nida')
            ->set('email', 'nida@example.com')
            ->set('phone', '0300-5555555')
            ->set('doctor_id', $doctor->id)
            ->set('stime', now()->addDay()->format('Y-m-d H:i'))
            ->set('address', 'Lahore')
            ->call('store_requested_appointment')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('message_logs', 2);
        $this->assertDatabaseHas('message_logs', ['channel' => 'sms', 'phone' => '0300-5555555']);
        $this->assertDatabaseHas('message_logs', ['channel' => 'whatsapp', 'phone' => '0300-5555555']);
        $this->assertDatabaseHas('requested_appointments', ['phone' => '0300-5555555']);
    }

    public function test_the_mobile_app_request_fires_both_channels(): void
    {
        $this->withoutMix();

        $doctor = \App\Models\doctor::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => \App\Models\employee::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'Dr. Latif',
                'email' => 'latif@example.com',
                'phone' => '0300-2222',
                'position' => 'doctor',
            ])->id,
        ]);

        $response = $this->postJson('/api/v1/appointments/request', [
            'name' => 'Farhan',
            'email' => 'farhan@example.com',
            'phone' => '0300-7777777',
            'doctor_id' => $doctor->id,
            'stime' => now()->addDay()->format('Y-m-d H:i'),
            'address' => 'Karachi',
        ]);

        $response->assertCreated();

        $this->assertDatabaseCount('message_logs', 2);
        $this->assertDatabaseHas('message_logs', ['channel' => 'sms', 'phone' => '0300-7777777']);
        $this->assertDatabaseHas('message_logs', ['channel' => 'whatsapp', 'phone' => '0300-7777777']);
    }
}