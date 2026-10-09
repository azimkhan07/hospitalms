<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\Events;
use App\Http\Livewire\Admins\MeetingCalendar;
use App\Models\Meeting;
use App\Models\Newsletter;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase: Jitsi video meetings + dashboard newsletter (PLAN.md 18g).
 *
 * A scheduled meeting becomes a Jitsi room; the organiser is the host and the
 * only one who can start/record, everyone else can only join once it is live,
 * and the newsletter + notifications go to exactly the roles that were picked.
 */
class MeetingVideoTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Meet Hospital', 'slug' => 'meet-hospital',
            'mode' => 'hospital', 'status' => 'active', 'geo_radius_meters' => 200,
        ]);
        $this->tenant->syncRequiredRoles([
            'admin', 'moderator', 'receptionist', 'doctor', 'nurse', 'pharmacist',
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

    public function test_scheduling_a_meeting_creates_a_room_and_newsletter(): void
    {
        $admin = $this->staff('admin');
        $doctor = $this->staff('doctor');

        Livewire::actingAs($admin)
            ->test(MeetingCalendar::class)
            ->set('selectedDate', Carbon::now()->addDay()->toDateString())
            ->set('title', 'Morning Huddle')
            ->set('location', 'Dean Office')
            ->set('time', '10:00')
            ->set('duration', '30')
            ->set('targetRoles', ['doctor'])
            ->call('saveMeeting')
            ->assertHasNoErrors();

        $meeting = Meeting::firstOrFail();

        $this->assertSame('Morning Huddle', $meeting->title);
        $this->assertNotNull($meeting->room_id);
        $this->assertSame($admin->id, $meeting->host_id);
        $this->assertSame(['doctor'], $meeting->targetRoleSlugs());

        $this->assertDatabaseHas('newsletters', [
            'meeting_id' => $meeting->id,
            'type' => 'meeting',
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $doctor->id,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $admin->id,
        ]);
    }

    public function test_non_targeted_role_does_not_see_the_newsletter(): void
    {
        $admin = $this->staff('admin');
        $pharmacist = $this->staff('pharmacist');

        Livewire::actingAs($admin)
            ->test(MeetingCalendar::class)
            ->set('selectedDate', Carbon::now()->addDay()->toDateString())
            ->set('title', 'Doctors Only')
            ->set('time', '11:00')
            ->set('duration', '30')
            ->set('targetRoles', ['doctor'])
            ->call('saveMeeting')
            ->assertHasNoErrors();

        $this->actingAs($pharmacist);

        $this->assertSame(0, Newsletter::visibleTo($pharmacist)->count());
        $this->assertSame(0, Meeting::visibleTo($pharmacist)->count());
    }

    public function test_only_the_host_can_start_and_others_can_only_join_when_live(): void
    {
        $admin = $this->staff('admin');
        $nurse = $this->staff('nurse');

        $meeting = Meeting::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Ward Round',
            'scheduled_at' => Carbon::now()->subMinutes(2),
            'duration_minutes' => 30,
            'status' => 'scheduled',
            'created_by' => $admin->id,
            'host_id' => $admin->id,
            'target_roles' => ['nurse'],
            'room_id' => 'hms-test-room',
        ]);

        $this->actingAs($nurse)
            ->get(route('admin_meeting_room', $meeting->id))
            ->assertRedirect();

        // Before the host starts, is it joinable? It is within the pre-open
        // window, but nobody has started it, so the guest still cannot join.
        $this->assertFalse($meeting->hasStarted());

        $this->actingAs($admin)
            ->get(route('admin_meeting_room', $meeting->id))
            ->assertOk()
            ->assertSee('hms-test-room');

        $this->assertTrue($meeting->fresh()->hasStarted());
        $this->assertTrue($meeting->fresh()->isJoinable());

        $this->actingAs($nurse)
            ->get(route('admin_meeting_room', $meeting->id))
            ->assertOk();
    }

    public function test_a_participant_who_is_not_targeted_is_forbidden(): void
    {
        $admin = $this->staff('admin');
        $receptionist = $this->staff('receptionist');

        $meeting = Meeting::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Private',
            'scheduled_at' => Carbon::now()->subMinutes(1),
            'duration_minutes' => 30,
            'status' => 'live',
            'created_by' => $admin->id,
            'host_id' => $admin->id,
            'target_roles' => ['doctor'],
            'room_id' => 'hms-private-room',
            'started_at' => Carbon::now(),
        ]);

        $this->actingAs($receptionist)
            ->get(route('admin_meeting_room', $meeting->id))
            ->assertForbidden();
    }

    public function test_calendar_event_can_spawn_a_meeting_and_newsletter(): void
    {
        $admin = $this->staff('admin');
        $doctor = $this->staff('doctor');

        Livewire::actingAs($admin)
            ->test(Events::class)
            ->set('title', 'Free Camp')
            ->set('type', 'general')
            ->set('startsOn', Carbon::now()->addDays(2)->toDateString())
            ->set('startsAt', '09:00')
            ->set('withMeeting', true)
            ->set('meetingRoles', ['doctor'])
            ->call('save')
            ->assertHasNoErrors();

        $meeting = Meeting::firstOrFail();

        $this->assertNotNull($meeting->calendar_event_id);
        $this->assertSame('Free Camp — online', $meeting->title);
        $this->assertSame(['doctor'], $meeting->targetRoleSlugs());
        $this->assertDatabaseHas('newsletters', ['meeting_id' => $meeting->id, 'type' => 'event']);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $doctor->id]);
    }

    public function test_meetings_api_reports_join_state_and_newsletters(): void
    {
        $admin = $this->staff('admin');

        $meeting = Meeting::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'API Meeting',
            'scheduled_at' => Carbon::now()->subMinutes(1),
            'duration_minutes' => 30,
            'status' => 'scheduled',
            'created_by' => $admin->id,
            'host_id' => $admin->id,
            'target_roles' => ['admin'],
            'room_id' => 'hms-api-room',
        ]);

        Newsletter::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Meeting scheduled: API Meeting',
            'type' => 'meeting',
            'target_roles' => ['admin'],
            'meeting_id' => $meeting->id,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'sanctum');

        $this->getJson('/api/v1/admin/meetings')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.is_host', true)
            ->assertJsonPath('data.0.provider', 'jitsi');

        $this->getJson('/api/v1/admin/newsletters')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.meeting_id', $meeting->id);

        $this->postJson('/api/v1/admin/meetings/'.$meeting->id.'/join')
            ->assertOk()
            ->assertJsonPath('data.has_started', true);

        $this->assertTrue($meeting->fresh()->hasStarted());
    }

    public function test_tenant_isolation_hides_meetings(): void
    {
        $admin = $this->staff('admin');

        $other = Tenant::create([
            'name' => 'Other Hospital', 'slug' => 'other-hospital',
            'mode' => 'hospital', 'status' => 'active', 'geo_radius_meters' => 200,
        ]);

        $otherAdmin = User::factory()->create([
            'tenant_id' => $other->id,
            'role_id' => Role::where('slug', 'admin')->value('id'),
            'is_active' => true,
        ]);

        Meeting::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Their meeting',
            'scheduled_at' => Carbon::now()->addDay(),
            'duration_minutes' => 30,
            'status' => 'scheduled',
            'created_by' => $admin->id,
            'host_id' => $admin->id,
            'target_roles' => ['admin'],
            'room_id' => 'hms-their-room',
        ]);

        $this->actingAs($otherAdmin);

        $this->assertSame(0, Meeting::visibleTo($otherAdmin)->count());
    }
}
