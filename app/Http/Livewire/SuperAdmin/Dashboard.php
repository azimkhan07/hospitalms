<?php

namespace App\Http\Livewire\SuperAdmin;

use App\Models\SystemError;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $stats = [
            'tenants' => Tenant::count(),
            'active' => Tenant::where('status', 'active')->count(),
            'hospitals' => Tenant::where('mode', 'hospital')->count(),
            'clinics' => Tenant::where('mode', 'clinic')->count(),
            'admins' => User::whereRelation('role', 'slug', 'admin')->count(),
            'unresolved' => SystemError::whereNull('resolved_at')->count(),
            'errors_24h' => SystemError::where('created_at', '>=', now()->subDay())->count(),
        ];

        // Whole-platform activity, read across every facility (the tenant
        // global scope is off in the platform panel).
        $platform = [
            'patients' => \App\Models\patient::acrossTenants()->count(),
            'appointments_today' => \App\Models\appointment::acrossTenants()->whereDate('intime', today())->count(),
            'revenue_today' => (float) \App\Models\payment::acrossTenants()
                ->where('status', 'paid')->whereDate('created_at', today())->sum('amount'),
            'staff' => User::where('is_active', true)->count(),
        ];

        return view('livewire.super-admin.dashboard', [
            'stats' => $stats,
            'platform' => $platform,
            'system' => $this->systemStats(),
            'recentTenants' => Tenant::withCount('users')->latest()->take(5)->get(),
            'recentErrors' => SystemError::with('tenant')->latest()->take(6)->get(),
        ]);
    }

    /**
     * Read-only runtime health the platform owner can eyeball without a shell:
     * versions, the active queue driver, database footprint, storage used and
     * the newest backup snapshot (written by `hms:backup`).
     *
     * @return array<string, mixed>
     */
    private function systemStats(): array
    {
        return [
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'queue' => (string) config('queue.default'),
            'timezone' => (string) config('app.timezone'),
            'db_size' => $this->databaseBytes(),
            'storage_used' => $this->storageBytes(),
            'last_backup' => $this->lastBackup(),
        ];
    }

    private function databaseBytes(): ?int
    {
        try {
            $row = DB::selectOne(
                'SELECT SUM(data_length + index_length) AS bytes
                 FROM information_schema.tables WHERE table_schema = DATABASE()'
            );

            return $row && $row->bytes !== null ? (int) $row->bytes : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function storageBytes(): int
    {
        $path = storage_path('app');

        if (! File::isDirectory($path)) {
            return 0;
        }

        return (int) collect(File::allFiles($path))->sum(fn ($f) => $f->getSize());
    }

    private function lastBackup(): ?array
    {
        $dir = storage_path('app/backups');

        if (! File::isDirectory($dir)) {
            return null;
        }

        $latest = collect(File::files($dir))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->first();

        if (! $latest) {
            return null;
        }

        return [
            'name' => $latest->getFilename(),
            'bytes' => $latest->getSize(),
            'at' => \Illuminate\Support\Carbon::createFromTimestamp($latest->getMTime()),
        ];
    }
}
