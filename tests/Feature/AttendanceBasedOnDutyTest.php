<?php

namespace Tests\Feature;

use App\Models\appointment;
use App\Models\Attendance;
use App\Models\doctor;
use App\Models\employee;
use App\Models\patient;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AttendanceRecorder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Attendance-based on-duty: a doctor is on duty exactly when their login has
 * checked in that day. Covers the recorder hook, the model probe, the queue
 * payload and the corrective sync command.
 */
class AttendanceBasedOnDutyTest extends TestCase
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

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Duty Hospital', 'slug' => 'duty-hospital',
            'mode' => 'hospital', 'status' => 'active', 'geo_radius_meters' => 200,
        ]);
        $this->tenant->syncRequiredRoles([
            'admin', 'moderator', 'receptionist', 'doctor', 'nurse', 'pharmacist',
        ]);

        $this->admin = $this->staff('admin');
        $this->reception = $this->staff('receptionist');
        $this->doctorUser = $this->staff('doctor');

        $this->doctor = $this->makeDoctor('Dr. Kulkarni', $this->doctorUser);
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
     * Doctor rows are stamped by BelongsToTenant under a signed-in user
     * (tenant_id is not in doctors' fillable), so create under a tenant user.
     */
    protected function makeDoctor(string $name, ?User $user = null): doctor
    {
        Auth::login($this->admin);

        $person = employee::create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'.'.uniqid().'@example.com',
            'phone' => '0300-'.random_int(1000000, 9999999),
            'position' => 'doctor',
        ]);

        return doctor::create([
            'employee_id' => $person->id,
            'user_id' => ($user ?? $this->staff('doctor'))->id,
        ]);
    }

    protected function attend(User $user, string $workDate, bool $closed = false): Attendance
    {
        return Attendance::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
            'work_date' => $workDate,
            'check_in_at' => now(),
            'check_out_at' => $closed ? now() : null,
            'source' => 'login',
        ]);
    }

    protected function appt(doctor $doctor, string $status, int $token): appointment
    {
        Auth::login($this->admin);

        return appointment::create([
            'patient_id' => patient::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'Patient '.$token,
                'phone' => '0300-'.random_int(1000000, 9999999),
            ])->id,
            'doctor_id' => $doctor->id,
            'intime' => now()->startOfDay()->addHours(9),
            'status' => $status,
            'token' => $token,
        ]);
    }

    public function test_check_in_puts_a_linked_doctor_on_duty(): void
    {
        $this->doctor->update(['on_duty' => false]);

        app(AttendanceRecorder::class)->checkIn($this->doctorUser);

        $this->assertTrue((bool) doctor::withoutGlobalScope('tenant')->find($this->doctor->id)->on_duty);

        // A receptionist has no doctor profile; the punch must not blow up.
        app(AttendanceRecorder::class)->checkIn($this->reception);
        $this->assertDatabaseHas('attendances', ['user_id' => $this->reception->id]);
    }

    public function test_check_out_takes_a_linked_doctor_off_duty(): void
    {
        $this->doctor->update(['on_duty' => true]);

        $recorder = app(AttendanceRecorder::class);
        $recorder->checkIn($this->doctorUser);
        $recorder->checkOut($this->doctorUser);

        $this->assertFalse((bool) doctor::withoutGlobalScope('tenant')->find($this->doctor->id)->on_duty);
    }

    public function test_on_attendance_today_reads_todays_check_ins(): void
    {
        $this->assertFalse($this->doctor->onAttendanceToday());

        $this->attend($this->doctorUser, now()->toDateString(), true);
        $this->assertTrue($this->doctor->fresh()->onAttendanceToday());

        $tomorrow = $this->makeDoctor('Dr. Tomorrow');
        $this->attend($tomorrow->user, now()->addDay()->toDateString());
        $this->assertFalse($tomorrow->fresh()->onAttendanceToday());

        $none = $this->makeDoctor('Dr. None');
        $this->assertFalse($none->fresh()->onAttendanceToday());
    }

    public function test_queue_api_reports_attendance_today_per_doctor(): void
    {
        app(AttendanceRecorder::class)->checkIn($this->doctorUser);
        $off = $this->makeDoctor('Dr. Off');

        $this->appt($this->doctor, 'waiting', 1);
        $this->appt($off, 'waiting', 2);

        Sanctum::actingAs($this->admin, ['*']);

        $this->getJson('/api/v1/admin/queue')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.doctor.id', $this->doctor->id)
            ->assertJsonPath('data.0.doctor.attendance_today', true)
            ->assertJsonPath('data.1.doctor.id', $off->id)
            ->assertJsonPath('data.1.doctor.attendance_today', false);
    }

    public function test_sync_command_aligns_on_duty_with_attendance(): void
    {
        $stale = $this->doctor;
        $stale->update(['on_duty' => true]);

        $off = $this->makeDoctor('Dr. Off');
        $off->update(['on_duty' => false]);

        $present = $this->makeDoctor('Dr. Present');
        $present->update(['on_duty' => false]);
        $this->attend($present->user, now()->toDateString());

        $this->artisan('hms:sync-on-duty')->assertExitCode(0);

        $this->assertFalse((bool) doctor::withoutGlobalScope('tenant')->find($stale->id)->on_duty);
        $this->assertFalse((bool) doctor::withoutGlobalScope('tenant')->find($off->id)->on_duty);
        $this->assertTrue((bool) doctor::withoutGlobalScope('tenant')->find($present->id)->on_duty);
    }

    public function test_check_out_without_an_open_session_returns_null(): void
    {
        $this->assertNull(app(AttendanceRecorder::class)->checkOut($this->doctorUser));
    }
}
