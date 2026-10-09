<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    protected $signature = 'hms:backup {--path= : Absolute target file (defaults to storage/app/backups/hms-YYYYMMDD-HHMMSS.sqljson)}';

    protected $description = 'Snapshot the platform: a portable JSON manifest of every tenant, plus a full mysqldump when the binary is available';

    public function handle(): int
    {
        $tenantCounts = [];
        foreach (Tenant::orderBy('id')->get() as $tenant) {
            $tenantCounts[$tenant->id] = [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'mode' => $tenant->mode,
                'counts' => $this->tenantCounts($tenant->id),
            ];
        }

        $manifest = [
            'generated_at' => now()->toDateTimeString(),
            'app' => config('app.name'),
            'schema' => 'shared',
            'counts_per_tenant' => $tenantCounts,
            'tables' => $this->population(),
        ];

        $path = $this->option('path') ?: storage_path('app/backups/hms-'.now()->format('Ymd-His').'.sqljson');
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $mysqldump = app()->runningUnitTests() ? null : $this->locateMysqldump();
        $full = false;
        if ($mysqldump) {
            $fullPath = substr($path, 0, -4).'.sql';
            $process = new Process([$mysqldump, '--user='.config('database.connections.mysql.username'), '--password='.config('database.connections.mysql.password'), '--host='.config('database.connections.mysql.host'), '--port='.config('database.connections.mysql.port'), config('database.connections.mysql.database')], null, null, null, 300);
            $process->run();
            if ($process->isSuccessful()) {
                file_put_contents($fullPath, $process->getOutput());
                $full = true;
                $this->info('Full SQL dump written to '.$fullPath);
            } else {
                $this->warn('mysqldump failed ('.Str::limit(trim($process->getErrorOutput()), 120).') - keeping the JSON manifest only.');
            }
        } else {
            $this->warn('mysqldump binary not found - wrote JSON manifest only. Set BACKUP_MYSQLDUMP to enable full dumps.');
        }

        $this->info('Backup manifest written to '.$path);

        $this->pruneOldBackups($dir);

        return self::SUCCESS;
    }

    /**
     * Retention: keep the newest two weeks of snapshots, drop the rest.
     */
    private function pruneOldBackups(string $dir): int
    {
        $keepDays = max(1, (int) config('hms.backup_keep_days', 14));
        $cutoff = now()->subDays($keepDays)->getTimestamp();
        $removed = 0;

        foreach (glob($dir.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            if (is_file($file) && @filemtime($file) < $cutoff) {
                if (@unlink($file)) {
                    $removed++;
                }
            }
        }

        if ($removed) {
            $this->info($removed.' backup file(s) older than '.$keepDays.' days removed.');
        }

        return $removed;
    }

    private function locateMysqldump(): ?string
    {
        $candidates = array_filter([
            getenv('BACKUP_MYSQLDUMP'),
            'D:\\xampp\\mysql\\bin\\mysqldump.exe',
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
        ]);

        foreach ($candidates as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function tenantCounts(int $tenantId): array
    {
        $tables = ['patients', 'bills', 'appointments', 'medicines', 'deliveries', 'calendar_events', 'accounting_vouchers', 'salary_vouchers', 'users'];
        $out = [];
        foreach ($tables as $table) {
            $out[$table] = (int) DB::table($table)->where('tenant_id', $tenantId)->count();
        }

        return $out;
    }

    private function population(): array
    {
        return collect(DB::select('SHOW TABLES'))
            ->map(function ($table) {
                $name = (string) array_values((array) $table)[0];

                return [$name, (int) DB::table($name)->count()];
            })
            ->sortBy(fn ($el) => $el[0])
            ->values()
            ->all();
    }
}