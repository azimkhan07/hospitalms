<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API mirror of the admin panel's Facilities tab (PLAN.md 9c.3): every clinic
 * and hospital the platform hosts and how staffed it is. Read-only.
 */
class FacilitiesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! hms_can('facilities')) {
            abort(403);
        }

        $facilities = Tenant::with(['clinicType', 'requirements.role'])
            ->withCount('users')
            ->when($request->string('search')->toString() !== '', function ($q) use ($request) {
                $term = '%'.mb_strtolower($request->string('search')->toString()).'%';
                $q->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(slug) LIKE ?', [$term]);
                });
            })
            ->when($request->string('mode')->toString() !== '', fn ($q) => $q->where('mode', $request->string('mode')->toString()))
            ->when($request->string('type')->toString() !== '', fn ($q) => $q->where('clinic_type_id', $request->string('type')->toString()))
            ->when($request->boolean('unassigned'), function ($q) {
                $q->whereDoesntHave('users', fn ($u) => $u->whereHas('role', fn ($r) => $r->where('slug', 'admin')));
            })
            ->orderBy('name')
            ->limit(100)
            ->get();

        $roleCounts = DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->whereNotNull('users.tenant_id')
            ->whereNull('users.deleted_at')
            ->groupBy('users.tenant_id', 'roles.slug')
            ->selectRaw('users.tenant_id, roles.slug, COUNT(*) as total')
            ->get()
            ->groupBy('tenant_id');

        $bedCounts = DB::table('beds')
            ->whereNull('deleted_at')
            ->groupBy('tenant_id')
            ->selectRaw('tenant_id, COUNT(*) as total')
            ->pluck('total', 'tenant_id');

        $data = $facilities->map(function (Tenant $t) use ($roleCounts, $bedCounts): array {
            $missing = $t->requirements
                ->reject(fn ($req) => ((int) ($roleCounts[$t->id] ?? collect())->firstWhere('slug', $req->role->slug)?->total ?? 0) > 0)
                ->map(fn ($req) => $req->role->slug)
                ->values();

            return [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'mode' => $t->mode,
                'mode_label' => $t->modeLabel(),
                'type' => $t->clinicType?->name,
                'status' => $t->status,
                'has_admin' => $t->admins()->exists(),
                'missing_roles' => $missing,
                'roles_needed' => $t->requirements->map(fn ($req) => $req->role->slug)->values(),
                'staff_count' => $t->users_count,
                'bed_count' => (int) ($bedCounts[$t->id] ?? 0),
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }
}