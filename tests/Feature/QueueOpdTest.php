<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\Appiontment;
use App\Http\Livewire\Admins\Consultations;
use App\Models\appointment;
use App\Models\doctor;
use App\Models\employee;
use App\Models\patient;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase: OPD queue (PLAN.md 18i). Tokens at check-in, the doctor's
 * call-next bell with a reception notification, reception sending the
 * patient in, follow-up dates becoming real confirmed appointments and the
 * 3-day auto-terminate sweep.
 */
class QueueOpdTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $admin;

    protected User $reception;

    protected User $doctorUser;

    protected doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Queue Hospital', 'slug' => 'queue-hospital',
            'mode' => 'hospital', 'status' => 'active', 'geo_radius_meters' => 200,
        ]);
        $this->tenant->syncRequiredRoles([
            'admin', 'moderator', 'receptionist', 'doctor', 'nurse', 'pharmacist',
        ]);

        $this->admin = $this->staff('admin');
        $this->reception = $this->staff('receptionist');
        $this->doctorUser = $this->staff('doctor');

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
            'user_id' => $this->doctorUser->id,
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
        return patient::create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'phone' => '0300-'.random_int(1000000, 9999999),
        ]);
    }

    protected function appt(patient $patient, string $status, ?int $token = null): appointment
    {
        // Create under a signed-in tenant user so BelongsToTenant stamps the
        // facility (mirrors production; tenant_id is not in the fillable).
        Auth::login($this->admin);

        return appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'intime' => now()->startOfDay()->addHours(9),
            'status' => $status,
            'token' => $token,
        ]);
    }

    public function test_check_in_assigns_daily_tokens_in_order(): void
    {
        $visits = [$this->appt($this->patient('Ramesh'), 'confirmed'), $this->appt($this->patient('Sita'), 'confirmed')];

        Livewire::actingAs($this->reception)
            ->test(Appiontment::class)
            ->call('markWaiting', $visits[0]->id)
            ->call('markWaiting', $visits[1]->id)
            ->assertHasNoErrors();

        $this->assertSame('waiting', $visits[0]->fresh()->status);
        $this->assertSame(1, $visits[0]->fresh()->token);
        $this->assertSame('waiting', $visits[1]->fresh()->status);
        $this->assertSame(2, $visits[1]->fresh()->token);
    }

    public function test_doctor_calls_the_oldest_waiting_and_reception_gets_bell(): void
    {
        $first = $this->appt($this->patient('Ramesh'), 'waiting', 1);
        $second = $this->appt($this->patient('Sita'), 'waiting', 2);

        Livewire::actingAs($this->doctorUser)
            ->test(Consultations::class)
            ->call('callNext')
            ->assertHasNoErrors();

        $this->assertSame('called', $first->fresh()->status);
        $this->assertNotNull($first->fresh()->called_at);
        $this->assertSame('waiting', $second->fresh()->status);

        Notification::assertSentTo($this->reception, \App\Notifications\AppointmentCalled::class);
    }

    public function test_reception_sends_a_called_patient_into_the_room(): void
    {
        $called = $this->appt($this->patient('Ramesh'), 'called', 1);
        $plain = $this->appt($this->patient('Sita'), 'waiting', 2);

        Livewire::actingAs($this->reception)
            ->test(Appiontment::class)
            ->call('sendIn', $called->id)
            ->assertHasNoErrors()
            ->call('sendIn', $plain->id)
            ->assertHasNoErrors();

        $this->assertSame('in_consult', $called->fresh()->status);
        $this->assertSame('waiting', $plain->fresh()->status);
    }

    public function test_follow_up_date_creates_a_confirmed_appointment_only_once(): void
    {
        $visit = $this->appt($this->patient('Ramesh'), 'in_consult');
        $followUp = now()->addDays(10)->format('Y-m-d');

        Livewire::actingAs($this->doctorUser)
            ->test(Consultations::class)
            ->set('openId', $visit->id)
            ->set('followUpAt', $followUp)
            ->call('saveConsult')
            ->assertHasNoErrors();

        $auto = appointment::where('patient_id', $visit->patient_id)->where('id', '!=', $visit->id)->firstOrFail();

        $this->assertSame($this->doctor->id, $auto->doctor_id);
        $this->assertSame('confirmed', $auto->status);
        $this->assertSame($followUp, $auto->intime->format('Y-m-d'));
        $this->assertStringContainsString('Auto follow-up', (string) $auto->notes);

        Livewire::actingAs($this->doctorUser)
            ->test(Consultations::class)
            ->set('openId', $visit->id)
            ->set('followUpAt', $followUp)
            ->call('saveConsult')
            ->assertHasNoErrors();

        $this->assertSame(
            1,
            appointment::where('patient_id', $visit->patient_id)
                ->where('id', '!=', $visit->id)
                ->where('status', 'confirmed')
                ->count()
        );
    }

    public function test_purge_command_terminates_stale_visits_not_regular_ones(): void
    {
        $stalePending = $this->appt($this->patient('Ramesh'), 'pending');
        $stalePending->update(['intime' => now()->subDays(5)]);

        $staleConfirmed = $this->appt($this->patient('Sita'), 'confirmed');
        $staleConfirmed->update(['intime' => now()->subDays(5)]);

        $future = $this->appt($this->patient('Sunil'), 'confirmed');
        $future->update(['intime' => now()->addDays(2)]);

        $waiting = $this->appt($this->patient('Uma'), 'waiting', 1);

        Artisan::call('hms:purge-stale-appointments');

        $this->assertSame('terminated', $stalePending->fresh()->status);
        $this->assertSame('terminated', $staleConfirmed->fresh()->status);
        $this->assertSame('confirmed', $future->fresh()->status);
        $this->assertSame('waiting', $waiting->fresh()->status);
    }

    public function test_doctor_toggles_own_duty(): void
    {
        $this->assertTrue((bool) $this->doctor->fresh()->on_duty);

        Livewire::actingAs($this->doctorUser)
            ->test(Consultations::class)
            ->call('toggleDuty')
            ->assertHasNoErrors();

        $this->assertFalse((bool) $this->doctor->fresh()->on_duty);
    }

    public function test_queue_api_mirrors_the_board(): void
    {
        $first = $this->appt($this->patient('Ramesh'), 'waiting', 1);
        $second = $this->appt($this->patient('Sita'), 'waiting', 2);

        Sanctum::actingAs($this->doctorUser, ['*']);
        $this->getJson('/api/v1/admin/queue')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->postJson('/api/v1/admin/queue/call-next')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('called', $first->fresh()->status);
        Notification::assertSentTo($this->reception, \App\Notifications\AppointmentCalled::class);

        Sanctum::actingAs($this->reception, ['*']);
        $this->postJson('/api/v1/admin/appointments/'.$first->id.'/send-in')
            ->assertOk();

        $this->assertSame('in_consult', $first->fresh()->status);

        $this->postJson('/api/v1/admin/appointments/'.$second->id.'/send-in')
            ->assertStatus(422);

        Sanctum::actingAs($this->doctorUser, ['*']);
        $this->patchJson('/api/v1/admin/doctors/on-duty')
            ->assertOk();

        $this->assertFalse((bool) $this->doctor->fresh()->on_duty);

        Sanctum::actingAs($this->reception, ['*']);
        $this->postJson('/api/v1/admin/queue/call-next')
            ->assertForbidden();
    }
}