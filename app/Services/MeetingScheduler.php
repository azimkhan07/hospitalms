<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\Newsletter;
use App\Models\User;
use App\Notifications\MeetingScheduled;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One place that turns a scheduled meeting (from the calendar or from an
 * event) into a room, a newsletter entry and the right set of notifications
 * (PLAN.md section 12 / 18g).
 */
class MeetingScheduler
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int>  $participantIds
     */
    public static function create(array $attributes, User $host, array $participantIds = []): Meeting
    {
        $meeting = DB::transaction(function () use ($attributes, $host, $participantIds) {
            $targetRoles = array_values(array_unique(array_filter($attributes['target_roles'] ?? [])));

            $meeting = Meeting::create(array_merge([
                'title' => 'Meeting',
                'scheduled_at' => Carbon::now(),
                'duration_minutes' => 30,
                'status' => 'scheduled',
                'provider' => 'jitsi',
            ], $attributes, [
                'created_by' => $host->id,
                'host_id' => $host->id,
                'target_roles' => $targetRoles ?: null,
                'room_id' => $attributes['room_id'] ?? Meeting::makeRoomId((string) ($attributes['title'] ?? 'meeting')),
            ]));

            if ($participantIds) {
                $meeting->users()->syncWithoutDetaching($participantIds);
            }

            self::announce($meeting, $host);

            return $meeting;
        });

        return $meeting;
    }

    /**
     * Publish the dashboard newsletter entry and notify everyone who should
     * see the meeting (participants + everyone in a targeted role).
     */
    public static function announce(Meeting $meeting, User $host): Newsletter
    {
        $newsletter = Newsletter::create([
            'title' => 'Meeting scheduled: '.$meeting->title,
            'body' => $meeting->agenda,
            'type' => $meeting->calendar_event_id ? 'event' : 'meeting',
            'target_roles' => $meeting->target_roles,
            'important' => true,
            'meeting_id' => $meeting->id,
            'calendar_event_id' => $meeting->calendar_event_id,
            'created_by' => $host->id,
        ]);

        self::recipients($meeting, $host)
            ->each(fn (User $user) => $user->notify(new MeetingScheduled($meeting)));

        return $newsletter;
    }

    /**
     * Participants plus every active user in a targeted role, minus the host.
     */
    public static function recipients(Meeting $meeting, User $host)
    {
        $participantIds = $meeting->participants()->pluck('user_id')->all();
        $roles = $meeting->targetRoleSlugs();

        return User::where('tenant_id', $host->tenant_id)
            ->where('is_active', true)
            ->where('id', '!=', $host->id)
            ->where(function ($query) use ($participantIds, $roles) {
                $query->whereIn('id', $participantIds);

                if ($roles) {
                    $query->orWhereHas('role', fn ($r) => $r->whereIn('slug', $roles));
                }
            })
            ->get();
    }
}
