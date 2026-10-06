<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

if (! function_exists('storage_url')) {
    /**
     * Build a public URL for a file stored on the "public" disk.
     * Accepts a bare relative path, an absolute URL, or null (falls back).
     */
    function storage_url(?string $path, ?string $fallback = null): string
    {
        $fallback = $fallback ?: 'default.png';

        if (blank($path)) {
            return asset('storage/'.$fallback);
        }

        if (Str::startsWith($path, ['http://', 'https://', '//', 'data:'])) {
            return $path;
        }

        return asset('storage/'.ltrim($path, '/'));
    }
}

if (! function_exists('hms_institution_mode')) {
    /**
     * The active institution mode: "clinic" or "hospital".
     *
     * The tenant row owns the mode (a Super Admin picks it during onboarding),
     * so the signed-in user's tenant wins. The settings row is only a fallback
     * for the public site, where nobody is authenticated and there is no tenant
     * to read. Without this split, flipping a tenant to "clinic" in the
     * Super Admin panel changed nothing, because everything still keyed off
     * the settings row.
     */
    function hms_institution_mode(): string
    {
        $modes = config('hms.modes', []);
        $valid = fn ($m) => is_string($m) && array_key_exists($m, $modes);

        try {
            $user = Auth::user();

            $tenantMode = $user && $user->tenant_id
                ? $user->tenant()->value('mode')
                : null;

            if ($valid($tenantMode)) {
                return $tenantMode;
            }
        } catch (\Throwable $e) {
            // fall through to the settings row
        }

        try {
            $stored = \App\Models\Settings::where('key', 'institution_mode')->value('value');
        } catch (\Throwable $e) {
            $stored = null;
        }

        return $valid($stored) ? $stored : 'hospital';
    }
}

if (! function_exists('hms_mode_config')) {
    function hms_mode_config(?string $mode = null): array
    {
        $mode ??= hms_institution_mode();

        return config('hms.modes.'.$mode, config('hms.modes.hospital', []));
    }
}

if (! function_exists('hms_institution_label')) {
    function hms_institution_label(?string $mode = null): string
    {
        return hms_mode_config($mode)['label'] ?? 'Hospital';
    }
}

if (! function_exists('hms_enabled_roles')) {
    /**
     * Role slugs available in the active mode. null = all roles.
     *
     * @return array<int, string>|null
     */
    function hms_enabled_roles(?string $mode = null): ?array
    {
        $roles = hms_mode_config($mode)['roles'] ?? null;

        return is_array($roles) ? array_values($roles) : null;
    }
}

if (! function_exists('hms_has_dean')) {
    /**
     * Does this tenant have a Dean (moderator) who can approve leave?
     *
     * The Dean is the first approver; the admin only steps in when the post is
     * vacant or disabled, which is the normal case in clinic mode where the
     * moderator role does not exist.
     */
    function hms_has_dean(?object $user = null): bool
    {
        $user ??= Auth::user();

        if (! $user || ! $user->tenant_id) {
            return false;
        }

        if (! hms_role_enabled('moderator')) {
            return false;
        }

        return \App\Models\User::where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->where('slug', 'moderator'))
            ->exists();
    }
}

if (! function_exists('hms_leave_approver_slugs')) {
    /**
     * Role slugs allowed to approve/reject a leave request right now.
     *
     * Dean first, admin as the fallback when no Dean is available. HR sits
     * alongside whichever is active because it handles the paperwork either way.
     *
     * @return array<int, string>
     */
    function hms_leave_approver_slugs(?object $user = null): array
    {
        $user ??= Auth::user();

        if (! $user) {
            return [];
        }

        $primary = hms_has_dean($user) ? 'moderator' : 'admin';

        return [$primary, 'hr'];
    }
}

if (! function_exists('hms_can_decide_leave')) {
    /**
     * May this user approve/reject a leave request?
     */
    function hms_can_decide_leave(?object $user = null): bool
    {
        $user ??= Auth::user();

        if (! $user || ! hms_can('leave.review', $user)) {
            return false;
        }

        return in_array($user->roleSlug(), hms_leave_approver_slugs($user), true);
    }
}

if (! function_exists('hms_leave_approver_label')) {
    /**
     * Who currently holds the first refusal, for the UI.
     */
    function hms_leave_approver_label(?object $user = null): string
    {
        $user ??= Auth::user();

        return hms_has_dean($user) ? 'Dean' : 'Admin';
    }
}

if (! function_exists('hms_role_enabled')) {
    /**
     * Is a role switched on for the tenant in play?
     *
     * Two gates, both must pass. The mode is the outer bound (PLAN.md section 16)
     * and the tenant's ticked role list narrows it further (PLAN.md section 9c.2),
     * so a dental clinic is narrower than "clinic" implies and nothing can ever
     * widen past the mode.
     *
     * A tenant with no ticked rows falls back to its mode rather than locking
     * itself out of every module -- an unset list must never mean "nothing".
     */
    function hms_role_enabled(?string $slug, ?string $mode = null, ?object $user = null): bool
    {
        if ($slug === null) {
            return false;
        }

        // The platform super admin is never restricted by an institution mode.
        if ($slug === 'super_admin') {
            return true;
        }

        $roles = hms_enabled_roles($mode);

        if ($roles !== null && ! in_array($slug, $roles, true)) {
            return false;
        }

        // No tenant in play (e.g. a console check, or the platform panel) means
        // the mode is the only gate available.
        $user ??= Auth::user();

        if (! $user || ! $user->tenant_id) {
            return true;
        }

        return hms_tenant_requires_role($slug, $user);
    }
}

if (! function_exists('hms_tenant_required_roles')) {
    /**
     * Role slugs the active tenant was configured with.
     *
     * Falls back to the mode's full role list when the tenant has no explicit
     * requirements recorded, so a tenant can never end up with nothing.
     *
     * @return array<int, string>
     */
    function hms_tenant_required_roles(?object $user = null): array
    {
        $user ??= Auth::user();

        if (! $user || ! $user->tenant_id) {
            return [];
        }

        $slugs = \App\Models\Tenant::requiredRoleSlugs($user->tenant_id);

        return $slugs ?: (hms_enabled_roles() ?? \App\Models\Role::pluck('slug')->all());
    }
}

if (! function_exists('hms_tenant_requires_role')) {
    /**
     * Did this tenant's owner tick this role at creation?
     *
     * Shares the fallback with hms_tenant_required_roles(): a tenant with no
     * recorded requirements is governed by its mode, never by nothing.
     */
    function hms_tenant_requires_role(string $slug, ?object $user = null): bool
    {
        $user ??= Auth::user();

        if (! $user || ! $user->tenant_id) {
            return false;
        }

        return in_array($slug, hms_tenant_required_roles($user), true);
    }
}

if (! function_exists('hms_can')) {
    /**
     * Check whether the current (or given) user may access a module.
     *
     * "*" grants everything. A dotted permission is never inherited from its
     * parent, so "meetings" means read-only while "meetings.manage" is write.
     * The active institution mode may further restrict roles and modules.
     */
    function hms_can(string $module, ?object $user = null): bool
    {
        $user ??= Auth::user();

        if (! $user) {
            return false;
        }

        // Platform super admin can access everything.
        if (method_exists($user, 'isPlatformAdmin') && $user->isPlatformAdmin()) {
            return true;
        }

        $slug = $user->roleSlug();

        if (! hms_role_enabled($slug, null, $user)) {
            return false;
        }

        $map = config('hms.'.$slug);

        if (! is_array($map)) {
            return false;
        }

        $modules = $map['modules'] ?? [];

        $allowed = in_array('*', $modules, true) || in_array($module, $modules, true);

        if (! $allowed && str_contains($module, '.')) {
            [$parent] = explode('.', $module, 2);
            // a dotted permission is only inherited from a wildcard parent like "a.*"
            $allowed = in_array($parent.'.*', $modules, true);
        }

        if (! $allowed) {
            return false;
        }

        // institution-mode module allowlist
        $modeModules = hms_mode_config()['modules'] ?? null;

        if (! is_array($modeModules)) {
            return true;
        }

        if (in_array($module, $modeModules, true)) {
            return true;
        }

        if (str_contains($module, '.')) {
            [$parent] = explode('.', $module, 2);

            return in_array($parent, $modeModules, true);
        }

        return false;
    }
}

if (! function_exists('hms_role_label')) {
    function hms_role_label(?object $user = null): string
    {
        $user ??= Auth::user();

        if (! $user) {
            return 'Guest';
        }

        $slug = $user->roleSlug();

        return config('hms.'.$slug.'.label') ?? ucfirst((string) ($slug ?: 'user'));
    }
}

if (! function_exists('hms_role_label_for_slug')) {
    /**
     * Display label for a role slug, without needing a signed-in user.
     *
     * Used by the platform screens that list roles a facility *could* have,
     * where no user of that role exists yet.
     */
    function hms_role_label_for_slug(string $slug): string
    {
        return config('hms.'.$slug.'.label') ?? ucfirst(str_replace('_', ' ', $slug));
    }
}

if (! function_exists('hms_tenant_brand')) {
    /**
     * Brand (name + logo) for the signed-in tenant.
     *
     * The logo uploaded by the platform super admin on the tenant record wins,
     * then whatever the tenant set in its own settings, then the packaged
     * default so the panel never renders a broken or empty brand.
     *
     * @return array{name: string, logo: string}
     */
    function hms_tenant_brand(): array
    {
        $tenant = Auth::user()?->tenant;
        $site = \App\Models\SiteContent::get();

        return [
            'name' => $tenant?->name ?: ($site['name'] ?? 'HMS'),
            'logo' => storage_url($tenant?->logo ?: ($site['logo'] ?? null), 'default.png'),
        ];
    }
}

if (! function_exists('hms_geo_distance_meters')) {
    /**
     * Great-circle distance between two coordinates, in metres.
     */
    function hms_geo_distance_meters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}

if (! function_exists('hms_geo_restricted')) {
    /**
     * Is this account only allowed to sign in from the hospital premises?
     *
     * The tenant admin runs the system and may work remotely, so they are
     * exempt. Everyone else is pinned to the hospital, unless the platform has
     * not pinned a location yet (then nobody is blocked).
     */
    function hms_geo_restricted(?object $user = null): bool
    {
        $user ??= Auth::user();

        if (! $user || $user->isPlatformAdmin()) {
            return false;
        }

        // The tenant admin runs the system and may work remotely.
        if ($user->roleSlug() === 'admin') {
            return false;
        }

        return $user->tenant?->latitude !== null
            && $user->tenant?->longitude !== null;
    }
}

if (! function_exists('hms_geo_check')) {
    /**
     * Check a submitted location against the tenant's premises.
     *
     * Returns null when the sign-in is allowed, otherwise a message explaining
     * why it was refused.
     */
    function hms_geo_check(?object $user, ?float $lat, ?float $lng): ?string
    {
        if (! hms_geo_restricted($user)) {
            return null;
        }

        if ($lat === null || $lng === null) {
            return 'We could not read your location. Turn on location for this site in your browser, then sign in again — staff may only sign in from the hospital.';
        }

        $tenant = $user->tenant;
        $radius = (int) ($tenant->geo_radius_meters ?: 200);
        $distance = hms_geo_distance_meters((float) $tenant->latitude, (float) $tenant->longitude, $lat, $lng);

        if ($distance > $radius) {
            return sprintf(
                'You are about %s away from %s. Staff may only sign in from the hospital (within %d m). Admin accounts can sign in from anywhere.',
                $distance >= 1000 ? round($distance / 1000, 1).' km' : round($distance).' m',
                $tenant->name,
                $radius
            );
        }

        return null;
    }
}

if (! function_exists('hms_attendance')) {
    /**
     * Attendance (presency) rules.
     */
    function hms_attendance(): array
    {
        return array_merge([
            'full_day_hours' => 8,
            'half_day_hours' => 4,
        ], config('hms.attendance', []));
    }

    /**
     * Classify a worked duration into a presence status.
     */
    function hms_attendance_status(?int $workedMinutes): string
    {
        if ($workedMinutes === null) {
            return 'pending';
        }

        $rules = hms_attendance();

        if ($workedMinutes >= $rules['full_day_hours'] * 60) {
            return 'present';
        }

        return $workedMinutes >= $rules['half_day_hours'] * 60 ? 'half_day' : 'absent';
    }
}

if (! function_exists('hms_sidebar_allows')) {
    /**
     * May the current user see this sidebar entry?
     *
     * An entry normally needs its own module, but may also list "anyModules" so
     * a page stays reachable for read-only roles. Leave, for instance, is
     * reachable by staff who apply for it and by reviewers who only decide on
     * it, and the admin has the review permission without the apply one.
     */
    function hms_sidebar_allows(array $item): bool
    {
        if (! empty($item['anyModules'])) {
            foreach ($item['anyModules'] as $module) {
                if (hms_can($module)) {
                    return true;
                }
            }
        }

        return hms_can($item['module']);
    }
}

if (! function_exists('hms_sidebar_tree')) {
    /**
     * Nested admin sidebar: single links and collapsible groups.
     * Groups are dropped when the user cannot see any of their children.
     */
    function hms_sidebar_tree(): array
    {
        $groups = [
            [
                'label' => 'Dashboard',
                'icon' => 'fa-home',
                'route' => 'admin_dashboard',
                'module' => 'dashboard',
            ],
            [
                'label' => 'Meetings & Calendar',
                'icon' => 'fa-calendar-alt',
                'route' => 'admin_meetings',
                'module' => 'meetings',
            ],
            [
                'label' => 'Leave',
                'icon' => 'fa-plane-departure',
                'route' => 'admin_leave',
                'module' => 'leave',
                // The admin does not apply for leave but still reviews it.
                'anyModules' => ['leave.review'],
            ],
            [
                'label' => 'Attendance',
                'icon' => 'fa-user-check',
                'route' => 'admin_attendance',
                'module' => 'attendance',
            ],
            [
                'label' => 'Staff',
                'icon' => 'fa-user-friends',
                'children' => [
                    ['label' => 'Doctor', 'route' => 'admin_staff', 'params' => ['role' => 'doctor'], 'module' => 'staff'],
                    ['label' => 'Nurse', 'route' => 'admin_staff', 'params' => ['role' => 'nurse'], 'module' => 'staff'],
                    ['label' => 'Moderator', 'route' => 'admin_staff', 'params' => ['role' => 'moderator'], 'module' => 'staff'],
                    ['label' => 'Receptionist', 'route' => 'admin_staff', 'params' => ['role' => 'receptionist'], 'module' => 'staff'],
                    ['label' => 'Pharmacist', 'route' => 'admin_staff', 'params' => ['role' => 'pharmacist'], 'module' => 'staff'],
                    ['label' => 'Accountant', 'route' => 'admin_staff', 'params' => ['role' => 'accountant'], 'module' => 'staff'],
                    ['label' => 'HR Manager', 'route' => 'admin_staff', 'params' => ['role' => 'hr'], 'module' => 'staff'],
                    ['label' => 'Store Keeper', 'route' => 'admin_staff', 'params' => ['role' => 'storekeeper'], 'module' => 'staff'],
                    ['label' => 'Laboratorist', 'route' => 'admin_staff', 'params' => ['role' => 'laboratorist'], 'module' => 'staff'],
                    ['label' => 'Employees', 'route' => 'employees', 'module' => 'employees'],
                    ['label' => 'HOD', 'route' => 'hods', 'module' => 'hods'],
                ],
            ],
            [
                'label' => 'Clinical',
                'icon' => 'fa-stethoscope',
                'children' => [
                    ['label' => 'Appointments', 'route' => 'appointment', 'module' => 'appointments'],
                    ['label' => 'Prescriptions', 'route' => 'admin_prescriptions', 'module' => 'prescriptions'],
                    ['label' => 'Patient History', 'route' => 'admin_history', 'module' => 'history'],
                    ['label' => 'Discharge History', 'route' => 'admin_discharges', 'module' => 'discharges'],
                    ['label' => 'Operation Report', 'route' => 'admin_operations_report', 'module' => 'operations'],
                    ['label' => 'Birth Report', 'route' => 'admin_birth_report', 'module' => 'births'],
                ],
            ],
            [
                'label' => 'Pharmacy',
                'icon' => 'fa-pills',
                'children' => [
                    ['label' => 'Medicine & Store', 'route' => 'medicinesStore', 'module' => 'medicines'],
                    ['label' => 'Dispense Counter', 'route' => 'admin_pharmacy', 'module' => 'prescriptions'],
                    ['label' => 'Expiry Alerts', 'route' => 'admin_expired_medicines', 'module' => 'expiry'],
                ],
            ],
            [
                'label' => 'In-Patient',
                'icon' => 'fa-procedures',
                'children' => [
                    ['label' => 'Rooms', 'route' => 'rooms', 'module' => 'rooms'],
                    ['label' => 'Beds', 'route' => 'patients_beds', 'module' => 'beds'],
                    ['label' => 'Blocks', 'route' => 'blocks', 'module' => 'blocks'],
                    ['label' => 'Departments', 'route' => 'departments', 'module' => 'departments'],
                ],
            ],
            [
                'label' => 'Diagnostics',
                'icon' => 'fa-x-ray',
                'children' => [
                    ['label' => 'Machines', 'route' => 'admin_machines', 'module' => 'machines'],
                    ['label' => 'Rate Card', 'route' => 'admin_investigations', 'module' => 'investigations'],
                    ['label' => 'Bed Reports', 'route' => 'admin_bed_reports', 'module' => 'bedreports'],
                ],
            ],
            [
                'label' => 'Front Desk',
                'icon' => 'fa-concierge-bell',
                'children' => [
                    ['label' => 'Patients', 'route' => 'admin_patients', 'module' => 'patients'],
                    ['label' => 'Requested Appointments', 'route' => 'requestedAppointment', 'module' => 'appointments'],
                    ['label' => 'Subscribers', 'route' => 'subscibers', 'module' => 'subscribers'],
                    ['label' => 'Messages', 'route' => 'contactedus', 'module' => 'messages'],
                ],
            ],
            [
                'label' => 'Finance',
                'icon' => 'fa-calculator',
                'children' => [
                    ['label' => 'Accountant Desk', 'route' => 'admin_accounting', 'module' => 'accounting'],
                    ['label' => 'Patient Bills', 'route' => 'patient_bills', 'module' => 'bills'],
                ],
            ],
            [
                'label' => 'Settings',
                'icon' => 'fa-cog',
                'route' => 'admin_settings',
                'module' => 'settings',
            ],
        ];

        $tree = [];

        foreach ($groups as $group) {
            if (empty($group['children'])) {
                if (hms_sidebar_allows($group)) {
                    $tree[] = $group;
                }

                continue;
            }

            $children = array_values(array_filter(
                $group['children'],
                function ($child) {
                    if (! hms_sidebar_allows($child)) {
                        return false;
                    }

                    $role = $child['params']['role'] ?? null;

                    return $role === null || hms_role_enabled($role);
                }
            ));

            if ($children) {
                $group['children'] = $children;
                $tree[] = $group;
            }
        }

        return $tree;
    }
}

if (! function_exists('hms_sidebar_items')) {
    /**
     * Flat sidebar entries filtered by the current user's role.
     */
    function hms_sidebar_items(): array
    {
        $flat = [];

        foreach (hms_sidebar_tree() as $node) {
            if (empty($node['children'])) {
                $flat[] = $node;

                continue;
            }

            foreach ($node['children'] as $child) {
                $flat[] = $child;
            }
        }

        return $flat;
    }
}

if (! function_exists('hms_sidebar_url')) {
    function hms_sidebar_url(array $item): string
    {
        if (empty($item['route'])) {
            return '#';
        }

        if (! empty($item['params'])) {
            return route($item['route'], $item['params']);
        }

        return route($item['route']);
    }
}