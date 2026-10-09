<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\doctor;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Attendance-based on-duty sweep: a doctor is on duty exactly when their
 * linked login checked in that day. Corrects any stale on_duty flag left
 * behind by manual toggles or a missed punch.
 */
class SyncOnDuty extends Command
{
    protected $signature = 'hms:sync-on-duty {--date= : The day to sync (defaults to today)}';

    protected $description = "Set each doctor's on-duty flag from that day's attendance";

    public function handle(): int
    {
        $date = $this->option('date') ?: Carbon::today()->toDateString();
        $today = Carbon::today()->toDateString();
        $count = 0;

        foreach (Tenant::orderBy('id')->get() as $tenant) {
            $doctors = doctor::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->get();

            foreach ($doctors as $doctor) {
                $onDuty = $date === $today
                    ? $doctor->onAttendanceToday()
                    : $this->checkedInOn($doctor, $date);

                if ((bool) $doctor->on_duty !== $onDuty) {
                    $doctor->update(['on_duty' => $onDuty]);
                }

                $count++;
            }
        }

        $this->info("{$count} doctors synced");

        return self::SUCCESS;
    }

    /**
     * Same rule as doctor::onAttendanceToday() but for an arbitrary day.
     */
    private function checkedInOn(doctor $doctor, string $date): bool
    {
        if (! $doctor->user_id) {
            return false;
        }

        return Attendance::where('user_id', $doctor->user_id)
            ->whereDate('work_date', $date)
            ->whereNotNull('check_in_at')
            ->exists();
    }
}
