<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SuperAdminController extends Controller
{
    public function showLogin()
    {
        if (Auth::check() && Auth::user()->isPlatformAdmin()) {
            return redirect()->route('superadmin.dashboard');
        }

        return view('superadmin.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'These credentials do not match our records.'])
                ->onlyInput('email');
        }

        $user = Auth::user();

        if (! $user->isPlatformAdmin()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['email' => 'This account is not a platform super admin.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('superadmin.dashboard'));
    }

    public function dashboard()
    {
        return view('superadmin.dashboard');
    }

    public function tenants()
    {
        return view('superadmin.tenants');
    }

    public function errors()
    {
        return view('superadmin.errors');
    }

    public function auditLogs()
    {
        return view('superadmin.audit-logs');
    }

    public function admins()
    {
        return view('superadmin.admins');
    }

    /**
     * Sales-level export of every facility (PLAN.md section 9c.5): mode, type,
     * who still needs an admin, the unstaffed roles it was built for, and
     * staff / bed numbers so a half-built facility is obvious in a spreadsheet.
     */
    public function exportTenants(Request $request)
    {
        $tenants = Tenant::with(['clinicType', 'requirements.role'])
            ->withCount('users')
            ->when($request->string('search')->toString() !== '', function ($q) use ($request) {
                $term = '%'.mb_strtolower($request->string('search')->toString()).'%';
                $q->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(slug) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(domain) LIKE ?', [$term]);
                });
            })
            ->when($request->string('status')->toString() !== '', fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->string('mode')->toString() !== '', fn ($q) => $q->where('mode', $request->string('mode')->toString()))
            ->when($request->string('type')->toString() !== '', fn ($q) => $q->where('clinic_type_id', $request->string('type')->toString()))
            ->when($request->boolean('unassigned'), function ($q) {
                $q->whereDoesntHave('users', fn ($u) => $u->whereHas('role', fn ($r) => $r->where('slug', 'admin')));
            })
            ->orderByDesc('id')
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

        $allotedCounts = DB::table('beds')
            ->where('status', 'alloted')
            ->whereNull('deleted_at')
            ->groupBy('tenant_id')
            ->selectRaw('tenant_id, COUNT(*) as total')
            ->pluck('total', 'tenant_id');

        $rows = [];
        $rows[] = ['Facility', 'Slug', 'Mode', 'Type', 'Status', 'Has admin', 'Missing roles',
            'Staff accounts', 'Beds', 'Beds alloted', 'Bed utilisation %', 'Created'];

        foreach ($tenants as $tenant) {
            $missing = $tenant->requirements
                ->map(function ($req) use ($roleCounts, $tenant) {
                    $have = (int) ($roleCounts[$tenant->id] ?? collect())->firstWhere('slug', $req->role->slug)?->total ?? 0;
                    return $have > 0 ? null : hms_role_label_for_slug($req->role->slug);
                })
                ->filter()
                ->implode(', ');

            $beds = (int) ($bedCounts[$tenant->id] ?? 0);
            $alloted = (int) ($allotedCounts[$tenant->id] ?? 0);

            $rows[] = [
                $tenant->name,
                $tenant->slug,
                $tenant->modeLabel(),
                $tenant->typeLabel(),
                $tenant->status,
                $tenant->admins()->count() > 0 ? 'yes' : 'no',
                $missing ?: 'none',
                $tenant->users_count,
                $beds,
                $alloted,
                $beds > 0 ? round(($alloted / $beds) * 100, 1) : 0,
                $tenant->created_at?->toDateString(),
            ];
        }

        $content = collect($rows)
            ->map(fn ($row) => implode(',', array_map(fn ($cell) => '"'.str_replace('"', '""', (string) $cell).'"', $row)))
            ->implode("\n");

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="hms-facilities-'.now()->format('Ymd-His').'.csv"',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('superadmin.login');
    }
}
