<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Institution modes
    |--------------------------------------------------------------------------
    | A super admin can run the system either as a small clinic or a full
    | multi-speciality hospital. "roles" = null means every role is allowed,
    | "modules" = null means every module is allowed.
    */
    'modes' => [
        'clinic' => [
            'label' => 'Clinic',
            'description' => 'Small clinic: receptionist, doctor, pharmacist and admin only.',
            'roles' => ['admin', 'receptionist', 'doctor', 'pharmacist'],
            'modules' => [
                'dashboard', 'meetings', 'meetings.manage', 'leave', 'leave.review',
                'attendance',
                'staff', 'appointments', 'prescriptions', 'history', 'medicines',
                'expiry', 'patients', 'bills', 'subscribers', 'messages', 'settings',
            ],
        ],
        'hospital' => [
            'label' => 'Multi-Speciality Hospital',
            'description' => 'Full hospital: every role and every module.',
            'roles' => null,
            'modules' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Attendance (presency)
    |--------------------------------------------------------------------------
    | Presence is derived from when a user signs in and out. Anyone who works
    | at least full_day_hours counts as present, half_day_hours as a half day
    | and anything less as absent.
    */
    'attendance' => [
        'full_day_hours' => 8,
        'half_day_hours' => 4,
        // Weekly off, used only so the monthly report can tell a genuine
        // absent day apart from a scheduled holiday. 0 = Sunday.
        'weekend_days' => [5, 6],
    ],

    'admin' => [
        'label' => 'Admin',
        'icon' => 'fa-user-shield',
        // Everything except "leave": the admin runs the system and works from
        // anywhere, so they do not apply for leave. They still review it.
        'modules' => [
            'dashboard', 'meetings', 'meetings.manage', 'attendance',
            'staff', 'appointments', 'prescriptions', 'history', 'medicines',
            'expiry', 'patients', 'bills', 'subscribers', 'messages', 'settings',
            'employees', 'departments', 'hods', 'rooms', 'beds', 'blocks',
            'nurses', 'operations', 'births', 'discharges', 'reports',
            'leave.review',
        ],
    ],
    'moderator' => [
        'label' => 'Moderator',
        'icon' => 'fa-user-tie',
        'modules' => [
            'dashboard', 'patients', 'employees', 'staff', 'departments', 'rooms',
            'beds', 'appointments', 'operations', 'births', 'reports', 'blocks',
            'prescriptions', 'history', 'discharges', 'meetings', 'meetings.manage',
            'attendance', 'leave', 'leave.review', 'subscribers', 'messages',
        ],
    ],
    'doctor' => [
        'label' => 'Doctor',
        'icon' => 'fa-user-md',
        'modules' => [
            'dashboard', 'patients', 'operations', 'births', 'attendance',
            'appointments', 'prescriptions', 'history', 'meetings', 'leave',
        ],
    ],
    'nurse' => [
        'label' => 'Nurse',
        'icon' => 'fa-user-nurse',
        'modules' => [
            'dashboard', 'patients', 'beds', 'rooms', 'nurses', 'attendance',
            'history', 'meetings', 'leave',
        ],
    ],
    'receptionist' => [
        'label' => 'Receptionist',
        'icon' => 'fa-concierge-bell',
        'modules' => [
            'dashboard', 'patients', 'appointments', 'subscribers', 'messages', 'attendance',
            'meetings', 'leave',
        ],
    ],
    'pharmacist' => [
        'label' => 'Pharmacist',
        'icon' => 'fa-pills',
        'modules' => [
            'dashboard', 'medicines', 'expiry', 'prescriptions', 'attendance', 'meetings', 'leave',
        ],
    ],
    'laboratorist' => [
        'label' => 'Laboratorist',
        'icon' => 'fa-flask',
        'modules' => [
            'dashboard', 'patients', 'history', 'attendance', 'meetings', 'leave',
        ],
    ],
    'accountant' => [
        'label' => 'Accountant',
        'icon' => 'fa-calculator',
        'modules' => [
            'dashboard', 'bills', 'attendance', 'meetings', 'leave',
        ],
    ],
    'storekeeper' => [
        'label' => 'Store Keeper',
        'icon' => 'fa-boxes-stacked',
        'modules' => [
            'dashboard', 'medicines', 'expiry', 'blocks', 'attendance', 'meetings', 'leave',
        ],
    ],
    'hr' => [
        'label' => 'HR Manager',
        'icon' => 'fa-users-cog',
        'modules' => [
            'dashboard', 'employees', 'staff', 'departments', 'hods', 'attendance', 'leave',
            'leave.review', 'meetings',
        ],
    ],
];