<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SnapshotController extends Controller
{
    /**
     * Read-only cross-tenant data snapshot for the platform panel. Counts run
     * against the raw tables (bypassing the auth scope) so the Super Admin sees
     * every facility's footprint without ever being able to edit it.
     */
    public function index(): JsonResponse
    {
        $tables = [
            'patients', 'bills', 'appointments', 'medicines',
            'deliveries', 'calendar_events', 'accounting_vouchers', 'salary_vouchers',
            'users', 'audit_logs',
        ];

        $counts = [];
        foreach ($tables as $table) {
            if (! $this->hasTenantColumn($table)) {
                continue;
            }

            foreach (DB::table($table)->where('tenant_id', '>', 0)->selectRaw('tenant_id, COUNT(*) c')->groupBy('tenant_id')->get() as $row) {
                $counts[intval($row->tenant_id)][$table] = (int) $row->c;
            }
        }

        $tenants = [];
        foreach (Tenant::orderBy('name')->get() as $tenant) {
            $tenants[] = [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'mode' => $tenant->mode,
                'status' => $tenant->status,
                'counts' => $counts[$tenant->id] ?? [],
            ];
        }

        $totals = [];
        foreach (array_keys($counts) as $tenantId) {
            foreach (($counts[$tenantId] ?? []) as $table => $count) {
                $totals[$table] = ($totals[$table] ?? 0) + $count;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'tables' => $tables,
                'tenants' => $tenants,
                'totals' => $totals,
            ],
        ]);
    }

    private function hasTenantColumn(string $table): bool
    {
        foreach (DB::select('SHOW COLUMNS FROM `'.$table.'`') as $column) {
            if (strtolower($column->Field) === 'tenant_id') {
                return true;
            }
        }

        return false;
    }
}