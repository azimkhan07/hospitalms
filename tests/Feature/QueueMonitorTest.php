<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\QueueMonitor;
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
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Reception queue monitor (PLAN.md 18i): the big-screen OPD board mirrors
 * today's waiting / called / in-consult tokens (waiting first, oldest token
 * first) and must not leak other days onto the display.
 */
class QueueMonitorTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $admin;

    protected User $reception;

    protected doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Queue Monitor Hospital', 'slug' => 'queue-monitor-hospital',
            'mode' => 'hospital', 'status' => 'active', 'geo_radius_meters' => 200,
        ]);
        $this->tenant->syncRequiredRoles([
            'admin', 'moderator', 'receptionist', 'doctor', 'nurse',
            'pharmacist', 'laboratorist',
        ]);

        $this->admin = $this->staff('admin');
        $this->reception = $this->staff('receptionist');

        // Doctor rows are stamped by BelongsToTenant under a signed-in user
        // (tenant_id is not in doctors' fillable), so create them while a
        // tenant user is the acting identity exactly like production.
        Auth::login($this->admin);

        $person = employee::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Dr. Monitor', 'email' => 'monitor@example.com',
            'phone' => '0300-7777777', 'position' => 'doctor',
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
        return patient::create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'phone' => '0300-'.random_int(1000000, 9999999),
        ]);
    }

    protected function appt(patient $patient, string $status, ?int $token = null, ?Carbon $intime = null): appointment
    {
        // Create under a signed-in tenant user so BelongsToTenant stamps the
        // facility (mirrors production; tenant_id is not in the fillable).
        Auth::login($this->admin);

        return appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'intime' => $intime ?? now()->startOfDay()->addHours(9),
            'status' => $status,
            'token' => $token,
        ]);
    }

    public function test_the_monitor_renders_for_reception_with_todays_waiting_patient(): void
    {
        $this->appt($this->patient('Ramesh Waiting'), 'waiting', 1);

        $this->actingAs($this->reception);

        Livewire::test(QueueMonitor::class)
            ->assertOk()
            ->assertSee('Reception Queue Monitor')
            ->assertSee('Ramesh Waiting');
    }

    public function test_the_counts_reflect_waiting_called_and_in_consult(): void
    {
        $this->appt($this->patient('Waiting One'), 'waiting', 1);
        $this->appt($this->patient('Waiting Two'), 'waiting', 2);
        $this->appt($this->patient('Called One'), 'called', 3);
        $this->appt($this->patient('Consult One'), 'in_consult', 4);

        $this->actingAs($this->reception);

        Livewire::test(QueueMonitor::class)
            ->assertViewHas('waitingCount', 2)
            ->assertViewHas('calledCount', 1)
            ->assertViewHas('inConsultCount', 1)
            ->assertSee('Waiting')
            ->assertSee('Called')
            ->assertSee('In Consult');
    }

    public function test_up_next_is_the_earliest_waiting_token(): void
    {
        $this->appt($this->patient('Later Waiting'), 'waiting', 7);
        $earliest = $this->appt($this->patient('Earliest Waiting'), 'waiting', 3);
        $this->appt($this->patient('Third Waiting'), 'waiting', 9);

        $this->actingAs($this->reception);

        Livewire::test(QueueMonitor::class)
            ->assertViewHas('nextToken', fn ($row) => $row->id === $earliest->id)
            ->assertViewHas('upNext', fn ($rows) => $rows->count() === 3
                && $rows->pluck('token')->all() === [3, 7, 9]);
    }

    public function test_tomorrows_appointment_is_not_listed(): void
    {
        $this->appt($this->patient('Today Waiting'), 'waiting', 1);
        $this->appt(
            $this->patient('Tomorrow Patient'),
            'waiting',
            2,
            Carbon::now()->addDay()->startOfDay()->addHours(9)
        );

        $this->actingAs($this->reception);

        Livewire::test(QueueMonitor::class)
            ->assertDontSee('Tomorrow Patient')
            ->assertSee('Today Waiting')
            ->assertViewHas('waitingCount', 1);
    }

    public function test_a_user_without_the_appointments_module_is_forbidden(): void
    {
        // The laboratorist role has no "appointments" module in config/hms.php.
        $this->actingAs($this->staff('laboratorist'));

        Livewire::test(QueueMonitor::class)->assertForbidden();
    }

    public function test_the_refresh_button_is_present_and_wired(): void
    {
        $this->actingAs($this->reception);

        Livewire::test(QueueMonitor::class)
            ->assertSee('Refresh')
            ->call('refresh')
            ->assertOk();
    }
}
