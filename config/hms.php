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

    'admin' => [
        'label' => 'Admin',
        'icon' => 'fa-user-shield',
        'modules' => ['*'],
    ],
    'moderator' => [
        'label' => 'Moderator',
        'icon' => 'fa-user-tie',
        'modules' => [
            'dashboard', 'patients', 'employees', 'staff', 'departments', 'rooms',
            'beds', 'appointments', 'operations', 'births', 'reports', 'blocks',
            'prescriptions', 'history', 'discharges', 'meetings', 'meetings.manage',
            'leave', 'leave.review', 'subscribers', 'messages',
        ],
    ],
    'doctor' => [
        'label' => 'Doctor',
        'icon' => 'fa-user-md',
        'modules' => [
            'dashboard', 'patients', 'operations', 'births',
            'appointments', 'prescriptions', 'history', 'meetings', 'leave',
        ],
    ],
    'nurse' => [
        'label' => 'Nurse',
        'icon' => 'fa-user-nurse',
        'modules' => [
            'dashboard', 'patients', 'beds', 'rooms', 'nurses',
            'history', 'meetings', 'leave',
        ],
    ],
    'receptionist' => [
        'label' => 'Receptionist',
        'icon' => 'fa-concierge-bell',
        'modules' => [
            'dashboard', 'patients', 'appointments', 'subscribers', 'messages',
            'meetings', 'leave',
        ],
    ],
    'pharmacist' => [
        'label' => 'Pharmacist',
        'icon' => 'fa-pills',
        'modules' => [
            'dashboard', 'medicines', 'expiry', 'prescriptions', 'meetings', 'leave',
        ],
    ],
    'laboratorist' => [
        'label' => 'Laboratorist',
        'icon' => 'fa-flask',
        'modules' => [
            'dashboard', 'patients', 'history', 'meetings', 'leave',
        ],
    ],
    'accountant' => [
        'label' => 'Accountant',
        'icon' => 'fa-calculator',
        'modules' => [
            'dashboard', 'bills', 'meetings', 'leave',
        ],
    ],
    'storekeeper' => [
        'label' => 'Store Keeper',
        'icon' => 'fa-boxes-stacked',
        'modules' => [
            'dashboard', 'medicines', 'expiry', 'blocks', 'meetings', 'leave',
        ],
    ],
    'hr' => [
        'label' => 'HR Manager',
        'icon' => 'fa-users-cog',
        'modules' => [
            'dashboard', 'employees', 'staff', 'departments', 'hods', 'leave',
            'leave.review', 'meetings',
        ],
    ],
];