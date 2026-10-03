<?php

namespace App\Listeners;

use App\Services\AttendanceRecorder;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;

class RecordAttendanceLogout
{
    public function __construct(protected AttendanceRecorder $attendance, protected Request $request) {}

    /**
     * Close the open attendance session when anyone signs out.
     *
     * Skips platform accounts, which have no tenant and so no attendance.
     */
    public function handle(Logout $event): void
    {
        $user = $event->user ?? $event->guard?->user();

        if (! $user || ! $user->tenant_id) {
            return;
        }

        $this->attendance->checkOut($user, ip: $this->request->ip());
    }
}
