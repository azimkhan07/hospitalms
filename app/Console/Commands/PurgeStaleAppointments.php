<?php

namespace App\Console\Commands;

use App\Models\appointment;
use Illuminate\Console\Command;

/**
 * OPD hygiene (PLAN.md section 6/18i): visits nobody ever started are dead
 * weight after 3 days. Roll them into `terminated` so the queue and the
 * "today" counts stay honest without the doctor having to close them.
 */
class PurgeStaleAppointments extends Command
{
    protected $signature = 'hms:purge-stale-appointments {--days=3 : Age threshold in days}';

    protected $description = 'Terminate pending/confirmed appointments that never started and are older than N days';

    public function handle(): int
    {
        $threshold = now()->subDays(max(1, (int) $this->option('days')));

        $count = appointment::withoutGlobalScopes()
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('intime', '<', $threshold)
            ->update(['status' => 'terminated']);

        $this->info("Purged {$count} stale appointment(s).");

        return self::SUCCESS;
    }
}