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
     */
    function hms_institution_mode(): string
    {
        $mode = null;

        try {
            $mode = \App\Models\Settings::where('key', 'institution_mode')->value('value');
        } catch (\Throwable $e) {
            $mode = null;
        }

        return array_key_exists((string) $mode, config('hms.modes', [])) ? $mode : 'hospital';
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

if (! function_exists('hms_role_enabled')) {
    function hms_role_enabled(?string $slug, ?string $mode = null): bool
    {
        if ($slug === null) {
            return false;
        }

        $roles = hms_enabled_roles($mode);

        return $roles === null || in_array($slug, $roles, true);
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

        $slug = $user->roleSlug();

        if (! hms_role_enabled($slug)) {
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
                    ['label' => 'Medicines Store', 'route' => 'medicinesStore', 'module' => 'medicines'],
                    ['label' => 'Expired Medicines', 'route' => 'admin_expired_medicines', 'module' => 'expiry'],
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
                'label' => 'Front Desk',
                'icon' => 'fa-concierge-bell',
                'children' => [
                    ['label' => 'Patients', 'route' => 'admin_patients', 'module' => 'patients'],
                    ['label' => 'Patient Bills', 'route' => 'patient_bills', 'module' => 'bills'],
                    ['label' => 'Requested Appointments', 'route' => 'requestedAppointment', 'module' => 'appointments'],
                    ['label' => 'Subscribers', 'route' => 'subscibers', 'module' => 'subscribers'],
                    ['label' => 'Messages', 'route' => 'contactedus', 'module' => 'messages'],
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
                if (hms_can($group['module'])) {
                    $tree[] = $group;
                }

                continue;
            }

            $children = array_values(array_filter(
                $group['children'],
                function ($child) {
                    if (! hms_can($child['module'])) {
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