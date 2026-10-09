<?php

namespace Tests\Feature;

use App\Contracts\MessageSender;
use App\Models\appointment;
use App\Models\doctor;
use App\Models\employee;
use App\Models\MessageLog;
use App\Models\patient;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AppointmentReminder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

/**
 * PLAN deferred item: the appointment reminder scheduler. The sweep must
 * ping an appointment exactly once (window + status + reminded_at gates),
 * reach the staff bell, attempt the patient over messaging and never blow
 * up when messaging is unconfigured or a facility has no active staff.
 */
class AppointmentReminderSchedulerTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $admin;

    protected User $reception;

    protected doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Reminder Hospital', 'slug' => 'reminder-hospital',
            'mode' => 'hospital', 'status' => 'active', 'geo_radius_meters' => 200,
        ]);
        $this->tenant->syncRequiredRoles([
            'admin', 'moderator', 'receptionist', 'doctor', 'nurse', 'pharmacist',
        ]);

        $this->admin = $this->staff('admin');
        $this->reception = $this->staff('receptionist');

        // Doctor rows are stamped by BelongsToTenant under a signed-in user
        // (tenant_id is not in doctors' fillable), so create them while a
        // tenant user is the acting identity exactly like production.
        Auth::login($this->admin);

        $person = employee::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Dr. Kulkarni', 'email' => 'kulkarni@example.com',
            'phone' => '0300-8888888', 'position' => 'doctor',
        ]);

        $this->doctor = doctor::create([
            'employee_id' => $person->id,
            'user_id' => $this->staff('doctor')->id,
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

    protected function patient(string $name): patient
    {
        Auth::login($this->admin);

        return patient::create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'phone' => '0300-'.random_int(1000000, 9999999),
        ]);
    }

    protected function appt(patient $patient, string $status, int $inMinutes): appointment
    {
        Auth::login($this->admin);

        return appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'intime' => now()->addMinutes($inMinutes),
            'status' => $status,
        ]);
    }

    public function test_upcoming_confirmed_appointment_is_reminded_exactly_once(): void
    {
        $visit = $this->appt($this->patient('Ramesh'), 'confirmed', 20);

        $this->artisan('hms:send-appointment-reminders')
            ->assertExitCode(0);

        $this->assertNotNull($visit->fresh()->reminded_at);
        Notification::assertSentTo($this->reception, AppointmentReminder::class);
        Notification::assertSentTo($this->admin, AppointmentReminder::class);

        $this->artisan('hms:send-appointment-reminders')
            ->assertExitCode(0);

        Notification::assertSentToTimes($this->reception, AppointmentReminder::class, 1);
    }

    public function test_appointment_outside_the_window_is_not_reminded(): void
    {
        $visit = $this->appt($this->patient('Sita'), 'confirmed', 180);

        $this->artisan('hms:send-appointment-reminders')
            ->assertExitCode(0);

        $this->assertNull($visit->fresh()->reminded_at);
        Notification::assertNotSentTo($this->reception, AppointmentReminder::class);
    }

    public function test_already_reminded_appointment_is_not_reminded_again(): void
    {
        $visit = $this->appt($this->patient('Sunil'), 'confirmed', 15);
        $visit->reminded_at = now()->subMinutes(10);
        $visit->save();
        $stamp = $visit->fresh()->reminded_at;

        $this->artisan('hms:send-appointment-reminders')
            ->assertExitCode(0);

        $this->assertSame($stamp, $visit->fresh()->reminded_at);
        Notification::assertNotSentTo($this->reception, AppointmentReminder::class);
    }

    public function test_cancelled_and_terminated_visits_are_not_reminded(): void
    {
        $cancelled = $this->appt($this->patient('Ramesh'), 'cancelled', 10);
        $terminated = $this->appt($this->patient('Sita'), 'terminated', 10);

        $this->artisan('hms:send-appointment-reminders')
            ->assertExitCode(0);

        $this->assertNull($cancelled->fresh()->reminded_at);
        $this->assertNull($terminated->fresh()->reminded_at);
        Notification::assertNotSentTo($this->reception, AppointmentReminder::class);
    }

    public function test_patient_is_attempted_on_their_phone(): void
    {
        $visit = $this->appt($this->patient('Ramesh'), 'confirmed', 10);
        $phone = $visit->patient->phone;

        $sender = $this->mock(MessageSender::class);
        $sender->shouldReceive('whatsapp')
            ->once()
            ->with($phone, Mockery::type('string'))
            ->andReturn(new MessageLog);
        $sender->shouldReceive('sms')
            ->once()
            ->with($phone, Mockery::type('string'))
            ->andReturn(new MessageLog);

        $this->artisan('hms:send-appointment-reminders')
            ->assertExitCode(0);

        $this->assertNotNull($visit->fresh()->reminded_at);
        Notification::assertSentTo($this->reception, AppointmentReminder::class);
    }

    public function test_sweep_survives_unconfigured_messaging_and_zero_staff(): void
    {
        config(['messaging.enabled' => false]);

        $visit = $this->appt($this->patient('Ramesh'), 'confirmed', 10);

        User::where('tenant_id', $this->tenant->id)->delete();

        $this->artisan('hms:send-appointment-reminders')
            ->assertExitCode(0);

        $this->assertNotNull($visit->fresh()->reminded_at);
        Notification::assertNotSentTo($this->reception, AppointmentReminder::class);
    }
}
