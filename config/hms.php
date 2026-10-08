<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Host → tenant map (single-host installs)
    |--------------------------------------------------------------------------
    | ResolveTenant middleware maps an HTTP host to a tenant id. Real installs
    | instead match `tenants.domain` / `tenants.subdomain`. Keys are lower-case.
    */
    'host_map' => [
        '127.0.0.1' => 1,
        'localhost' => 1,
    ],

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
                'attendance', 'calendar', 'calendar.manage',
'staff', 'appointments', 'prescriptions', 'history', 'medicines', 'medicines.manage',
'expiry', 'patients', 'bills', 'accounting', 'subscribers', 'messages', 'settings',
'reports', 'deliveries', 'deliveries.manage', 'schemes', 'printout', 'facilities',
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
        //
        // "rooms" and "beds" are deliberately READ-ONLY for the admin (PLAN.md
        // section 9b): no "beds.manage" and no "beds.allocate", so they can
        // read occupancy but never create a room or hand a bed to a patient.
        'modules' => [
            'dashboard', 'meetings', 'meetings.manage', 'attendance',
            'staff', 'staff.manage',
            'appointments', 'prescriptions', 'history', 'medicines', 'medicines.manage',
            'expiry', 'patients', 'bills', 'accounting', 'subscribers', 'messages', 'settings',
            'employees', 'departments', 'hods', 'rooms', 'beds', 'blocks',
            'nurses', 'operations', 'births', 'discharges', 'reports',
            'leave.review', 'calendar', 'calendar.manage',
            'deliveries', 'deliveries.manage',
            // Read-only on the diagnostic setup, same deal as rooms/beds: the
            // admin can see the rate card and the ICU reports, the Dean owns
            // the machines and the prices (PLAN.md 9d.4).
            'machines', 'investigations', 'bedreports',
            // Angio (cath lab) machines and Government schemes (yojna): the
            // admin reads and manages patients/appointments; the Dean owns the
            // angio machine master and the accountant books scheme money.
            'angio', 'schemes', 'printout', 'facilities',
        ],
    ],
    'moderator' => [
        'label' => 'Moderator',
        'icon' => 'fa-user-tie',
        'modules' => [
            'dashboard', 'patients', 'employees', 'staff', 'staff.manage',
            'departments', 'rooms',
            'beds', 'beds.manage', 'beds.allocate',
            'machines', 'machines.manage',
            'investigations', 'investigations.manage',
            'bedreports',
            'angio', 'angio.manage', 'schemes', 'schemes.manage',
            'appointments', 'operations', 'births', 'reports', 'blocks',
            'prescriptions', 'history', 'discharges', 'meetings', 'meetings.manage',
            'attendance', 'leave', 'leave.review', 'subscribers', 'messages',
            'calendar', 'calendar.manage', 'deliveries', 'deliveries.manage',
        ],
    ],
'doctor' => [
        'label' => 'Doctor',
        'icon' => 'fa-user-md',
        'modules' => [
'dashboard', 'patients', 'operations', 'births', 'attendance',
        'appointments', 'prescriptions', 'history', 'meetings', 'calendar', 'leave',
        'bedreports', 'printout',
        ],
    ],
'nurse' => [
        'label' => 'Nurse',
        'icon' => 'fa-user-nurse',
        'modules' => [
'dashboard', 'patients', 'beds', 'beds.status', 'rooms', 'nurses',
        'attendance', 'history', 'meetings', 'calendar', 'leave',
        'machines', 'bedreports', 'printout',
        ],
    ],
    'receptionist' => [
        'label' => 'Receptionist',
        'icon' => 'fa-concierge-bell',
'modules' => [
            'dashboard', 'patients', 'appointments', 'subscribers', 'messages', 'attendance',
            'meetings', 'calendar', 'deliveries', 'leave',
            // The bed map is read-only here; allocation itself is allowed so
            // the counter can hand a free bed to a patient (PLAN.md section 9b).
            'rooms', 'beds', 'beds.allocate', 'printout',
        ],
    ],
    'pharmacist' => [
        'label' => 'Pharmacist',
        'icon' => 'fa-pills',
        'modules' => [
            'dashboard', 'medicines', 'expiry', 'prescriptions', 'attendance', 'meetings', 'calendar', 'leave', 'printout',
        ],
    ],
    'laboratorist' => [
        'label' => 'Laboratorist',
        'icon' => 'fa-flask',
        'modules' => [
            'dashboard', 'patients', 'history', 'attendance', 'meetings', 'calendar', 'leave',
        ],
    ],
'accountant' => [
        'label' => 'Accountant',
        'icon' => 'fa-calculator',
        'modules' => [
            'dashboard', 'bills', 'accounting', 'patients', 'staff',
            'attendance', 'meetings', 'calendar', 'leave', 'reports',
            'schemes', 'schemes.manage', 'printout',
        ],
    ],
    'storekeeper' => [
        'label' => 'Store Keeper',
        'icon' => 'fa-boxes-stacked',
        'modules' => [
            'dashboard', 'medicines', 'medicines.manage', 'expiry', 'blocks',
            'deliveries', 'deliveries.manage', 'attendance', 'meetings', 'calendar', 'leave', 'printout',
        ],
    ],
    'hr' => [
        'label' => 'HR Manager',
        'icon' => 'fa-users-cog',
        'modules' => [
            'dashboard', 'employees', 'staff', 'departments', 'hods', 'attendance', 'leave',
            'leave.review', 'meetings', 'calendar',
        ],
    ],
];