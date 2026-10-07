<?php

namespace Tests\Feature;

use App\Models\CalendarEvent;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 9 acceptance: the Dean (moderator) runs blood-donation camps and
 * visiting-doctor events off the shared calendar; every staff member can read
 * them, features that change them are Dean/admin only, and the mobile app sees
 * the same month through the API mirror (PLAN.md 12).
 */
class CalendarEventTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Camps Hospital', 'slug' => 'camps-hospital',
            'mode' => 'hospital', 'status' => 'active', 'geo_radius_meters' => 200,
        ]);
        $this->tenant->syncRequiredRoles([
            'admin', 'moderator', 'doctor',
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

    public function test_the_dean_can_reach_readers_and_creates_events_for_a_date(): void
    {
        $dean = $this->staff('moderator');

        Livewire::actingAs($dean)
            ->test(\App\Http\Livewire\Admins\Events::class)
            ->call('selectDate', '2026-12-18')
            ->set('title', 'Blood Donation Camp')
            ->set('type', CalendarEvent::BLOOD_CAMP)
            ->set('startsAt', '09:30')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('calendar_events', [
            'title' => 'Blood Donation Camp',
            'type' => CalendarEvent::BLOOD_CAMP,
            'color' => '#d9534f',
        ]);

        $camp = CalendarEvent::where('title', 'Blood Donation Camp')->first();
        $this->assertSame('2026-12-18', $camp->starts_at->toDateString());
        $this->assertSame('09:30', $camp->starts_at->format('H:i'));
    }

    public function test_blood_camp_and_visiting_doctor_land_on_their_days_and_readers_see_them(): void
    {
        $dean = $this->staff('moderator');
        $this->actingAs($dean);

        CalendarEvent::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Free Blood Camp',
            'type' => CalendarEvent::BLOOD_CAMP,
            'color' => CalendarEvent::colorFor(CalendarEvent::BLOOD_CAMP),
            'starts_at' => Carbon::parse('2026-12-14 09:00:00'),
            'created_by' => $dean->id,
        ]);
        CalendarEvent::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Visiting Cardiologist',
            'type' => CalendarEvent::VISITING,
            'color' => CalendarEvent::colorFor(CalendarEvent::VISITING),
            'starts_at' => Carbon::parse('2026-12-20 11:00:00'),
            'created_by' => $dean->id,
        ]);

        $doctor = $this->staff('doctor');

        Livewire::actingAs($doctor)
            ->test(\App\Http\Livewire\Admins\Events::class)
            ->set('monthCursor', '2026-12-01')
            ->call('$refresh')
            ->assertSeeHtml('Free Blood Camp')
            ->assertSeeHtml('Visiting Cardiologist');
    }

    public function test_a_read_only_doctor_cannot_create_or_delete_events(): void
    {
        $doctor = $this->staff('doctor');

        Livewire::actingAs($doctor)
            ->test(\App\Http\Livewire\Admins\Events::class)
            ->set('title', 'Sneaky Event')
            ->call('save')
            ->assertForbidden();

        $dean = $this->staff('moderator');
        $this->actingAs($dean);

        CalendarEvent::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Existing Camp',
            'type' => CalendarEvent::GENERAL,
            'color' => '#0f7fd4',
            'starts_at' => now(),
            'created_by' => $dean->id,
        ]);

        Livewire::actingAs($doctor)
            ->test(\App\Http\Livewire\Admins\Events::class)
            ->call('removeEvent', CalendarEvent::first()->id)
            ->assertForbidden();
    }

    public function test_event_title_and_date_are_required(): void
    {
        $dean = $this->staff('moderator');

        Livewire::actingAs($dean)
            ->test(\App\Http\Livewire\Admins\Events::class)
            ->set('title', '')
            ->set('startsOn', '')
            ->call('save')
            ->assertHasErrors(['title', 'startsOn']);
    }

    public function test_events_can_be_edited_and_removed(): void
    {
        $dean = $this->staff('moderator');
        $this->actingAs($dean);

        $event = CalendarEvent::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Old Title',
            'type' => CalendarEvent::GENERAL,
            'color' => '#0f7fd4',
            'starts_at' => Carbon::parse('2026-12-10 10:00:00'),
            'created_by' => $dean->id,
        ]);

        Livewire::actingAs($dean)
            ->test(\App\Http\Livewire\Admins\Events::class)
            ->call('openEvent', $event->id)
            ->set('title', 'New Title')
            ->set('startsAt', '15:00')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('calendar_events', ['title' => 'New Title']);
        $this->assertDatabaseMissing('calendar_events', ['title' => 'Old Title']);

        Livewire::actingAs($dean)
            ->test(\App\Http\Livewire\Admins\Events::class)
            ->call('removeEvent', $event->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('calendar_events', ['title' => 'New Title']);
    }

    public function test_the_calendar_api_mirrors_the_month(): void
    {
        $dean = $this->staff('moderator');
        $this->actingAs($dean);

        CalendarEvent::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Ramadan Blood Camp',
            'type' => CalendarEvent::BLOOD_CAMP,
            'color' => '#d9534f',
            'starts_at' => Carbon::parse('2026-12-02 08:00:00'),
            'created_by' => $dean->id,
        ]);

        $this->actingAs($dean, 'sanctum');

        $this->getJson(route('api.v1.admin.calendar-events', ['month' => '2026-12']))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.title', 'Ramadan Blood Camp')
            ->assertJsonPath('data.0.type_label', 'Blood donation camp');
    }
}