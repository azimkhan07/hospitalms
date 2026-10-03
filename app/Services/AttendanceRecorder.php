<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Support\Carbon;

class AttendanceRecorder
{
    /**
     * Open a session on sign-in.
     *
     * A stale open row (browser closed without logging out) is closed first so
     * nobody ends up with two live sessions on the same day.
     */
    public function checkIn(User $user, ?float $lat = null, ?float $lng = null, ?string $ip = null, string $source = 'login'): Attendance
    {
        $this->closeOpenSessions($user);

        $tenant = $user->tenant;

        return Attendance::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'work_date' => Carbon::today()->toDateString(),
            'check_in_at' => Carbon::now(),
            'check_in_latitude' => $lat,
            'check_in_longitude' => $lng,
            'check_in_ip' => $ip,
            'check_in_distance_meters' => $this->distance($tenant, $lat, $lng),
            'source' => $source,
        ]);
    }

    /**
     * Close the user's open session, if any.
     */
    public function checkOut(User $user, ?float $lat = null, ?float $lng = null, ?string $ip = null): ?Attendance
    {
        $open = $user->attendances()->whereNull('check_out_at')->latest('check_in_at')->first();

        if (! $open) {
            return null;
        }

        $open->update([
            'check_out_at' => Carbon::now(),
            'check_out_latitude' => $lat,
            'check_out_longitude' => $lng,
            'check_out_ip' => $ip,
            'check_out_distance_meters' => $this->distance($user->tenant, $lat, $lng),
        ]);

        return $open->fresh();
    }

    /**
     * Close anything left open so a user never has two live sessions.
     *
     * Runs before the new row is created, so there is no race with the session
     * being opened right now. Someone signing in on a second device therefore
     * ends the first session rather than having their hours counted twice.
     */
    public function closeOpenSessions(User $user): void
    {
        $user->attendances()
            ->whereNull('check_out_at')
            ->get()
            ->each(fn (Attendance $a) => $a->update([
                'check_out_at' => Carbon::now(),
                'source' => $a->source.'+relogin',
            ]));
    }

    private function distance($tenant, ?float $lat, ?float $lng): ?int
    {
        if ($lat === null || $lng === null || $tenant?->latitude === null || $tenant?->longitude === null) {
            return null;
        }

        return (int) round(hms_geo_distance_meters((float) $tenant->latitude, (float) $tenant->longitude, $lat, $lng));
    }
}
