<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\DutyRoster;
use App\Models\doctor;
use App\Models\DutyRoster as DutyRosterModel;
use App\Models\employee;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Weekly duty roster: the admin week board (doctor x shift per date) and the
 * mobile API mirror, both tenant-scoped.
 */
class DutyRosterTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $admin;

    protected User $doctorUser;

    protected doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Roster Hospital', 'slug' => 'roster-hospital',
            'mode' => 'hospital', 'status' => 'active', 'geo_radius_meters' => 200,
        ]);
        $this->tenant->syncRequiredRoles([
            'admin', 'moderator', 'receptionist', 'doctor', 'nurse',
            'pharmacist', 'laboratorist',
        ]);

        $this->admin = $this->staff('admin');
        $this->doctorUser = $this->staff('doctor');

        // Doctor rows are stamped by BelongsToTenant under a signed-in user, so
        // create the profile while the admin is the acting identity.
        Auth::login($this->admin);

        $person = employee::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Dr. Roster', 'email' => 'roster@example.com',
            'phone' => '0300-1234567', 'position' => 'doctor',
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

    protected function monday(): string
    {
        return now()->startOfWeek(Carbon::MONDAY)->toDateString();
    }

    public function test_the_week_board_renders_for_admin(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(DutyRoster::class)
            ->assertOk()
            ->assertSee('Duty Roster')
            ->assertSee('Morning')
            ->assertViewHas('days', fn ($days) => $days->count() === 7);
    }

    public function test_saving_a_shift_persists_and_appears_on_the_board(): void
    {
        $date = $this->monday();

        $this->actingAs($this->admin);

        Livewire::test(DutyRoster::class)
            ->set('doctorId', $this->doctor->id)
            ->set('dutyDate', $date)
            ->set('shift', 'night')
            ->call('save')
            ->assertHasNoErrors()
            ->assertViewHas('entries', fn ($entries) => isset($entries[$this->doctor->id][$date]));

        $this->assertDatabaseHas('duty_rosters', [
            'tenant_id' => $this->tenant->id,
            'doctor_id' => $this->doctor->id,
            'shift' => 'night',
            'duty_date' => $date,
        ]);
    }

    public function test_saving_the_same_doctor_and_date_overwrites(): void
    {
        $date = $this->monday();

        $this->actingAs($this->admin);

        Livewire::test(DutyRoster::class)
            ->set('doctorId', $this->doctor->id)
            ->set('dutyDate', $date)
            ->set('shift', 'morning')
            ->call('save')
            ->assertHasNoErrors();

        Livewire::test(DutyRoster::class)
            ->set('doctorId', $this->doctor->id)
            ->set('dutyDate', $date)
            ->set('shift', 'evening')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, DutyRosterModel::where('doctor_id', $this->doctor->id)
            ->whereDate('duty_date', $date)
            ->count());

        $this->assertDatabaseHas('duty_rosters', [
            'doctor_id' => $this->doctor->id,
            'duty_date' => $date,
            'shift' => 'evening',
        ]);
    }

    public function test_the_api_lists_roster_rows_filtered_by_date(): void
    {
        $monday = $this->monday();
        $nextWeek = Carbon::parse($monday)->addWeek()->toDateString();

        Auth::login($this->admin);

        DutyRosterModel::create([
            'tenant_id' => $this->tenant->id, 'doctor_id' => $this->doctor->id,
            'shift' => 'morning', 'duty_date' => $monday,
        ]);
        DutyRosterModel::create([
            'tenant_id' => $this->tenant->id, 'doctor_id' => $this->doctor->id,
            'shift' => 'night', 'duty_date' => $nextWeek,
        ]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/admin/duty-rosters?from='.$monday.'&to='.Carbon::parse($monday)->addDays(6)->toDateString())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.doctor_name', 'Dr. Roster')
            ->assertJsonPath('data.0.shift', 'morning');
    }

    public function test_the_api_creates_and_deletes_a_roster_row(): void
    {
        $date = $this->monday();

        $otherTenant = Tenant::create([
            'name' => 'Other Hospital', 'slug' => 'other-hospital',
            'mode' => 'hospital', 'status' => 'active', 'geo_radius_meters' => 200,
        ]);

        Auth::login($this->admin);

        $foreign = DutyRosterModel::create([
            'tenant_id' => $otherTenant->id,
            'doctor_id' => null,
            'department' => 'General',
            'shift' => 'off',
            'duty_date' => $date,
        ]);

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/admin/duty-rosters', [
            'doctor_id' => $this->doctor->id,
            'shift' => 'evening',
            'duty_date' => $date,
            'start_time' => '14:00',
            'end_time' => '20:00',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $row = DutyRosterModel::where('doctor_id', $this->doctor->id)
            ->whereDate('duty_date', $date)
            ->firstOrFail();

        $this->deleteJson('/api/v1/admin/duty-rosters/'.$row->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('duty_rosters', ['id' => $row->id]);

        $this->deleteJson('/api/v1/admin/duty-rosters/'.$foreign->id)
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_the_page_is_forbidden_without_the_attendance_module(): void
    {
        // "auditor" is a real role slug with no config/hms.php entry, so the
        // role itself is enabled but grants no modules.
        $role = Role::create([
            'name' => 'Auditor', 'slug' => 'auditor', 'level' => 90,
            'module' => 'none', 'description' => 'Read-only outsider.',
        ]);

        $this->tenant->syncRequiredRoles(['admin', 'auditor']);

        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(DutyRoster::class)->assertForbidden();
    }

    public function test_the_validator_rejects_a_missing_shift(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(DutyRoster::class)
            ->set('doctorId', $this->doctor->id)
            ->set('dutyDate', $this->monday())
            ->set('shift', '')
            ->call('save')
            ->assertHasErrors(['shift']);

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/admin/duty-rosters', [
            'doctor_id' => $this->doctor->id,
            'duty_date' => $this->monday(),
        ])->assertStatus(422);
    }
}
