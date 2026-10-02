<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Hospital roles. level = authority (0 highest - 100 lowest).
     *
     * @return void
     */
    public function run()
    {
        $roles = [
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'level' => 0,
                'module' => 'all',
                'description' => 'Super admin. Full system control, roles, settings and all hospital data.',
            ],
            [
                'name' => 'Moderator',
                'slug' => 'moderator',
                'level' => 10,
                'module' => 'all',
                'description' => 'Dean of the hospital. Oversees all departments and staff, but cannot change system roles.',
            ],
            [
                'name' => 'Doctor',
                'slug' => 'doctor',
                'level' => 20,
                'module' => 'clinical',
                'description' => 'Examines patients, records diagnoses, prescriptions and operation reports.',
            ],
            [
                'name' => 'Nurse',
                'slug' => 'nurse',
                'level' => 30,
                'module' => 'clinical',
                'description' => 'Patient care, ward and bed management, nursing records.',
            ],
            [
                'name' => 'Receptionist',
                'slug' => 'receptionist',
                'level' => 35,
                'module' => 'front-desk',
                'description' => 'Front desk: patient registration, appointments and enquiries.',
            ],
            [
                'name' => 'Pharmacist',
                'slug' => 'pharmacist',
                'level' => 40,
                'module' => 'pharmacy',
                'description' => 'Medicine store, stock, dispensing and expiry control.',
            ],
            [
                'name' => 'Laboratorist',
                'slug' => 'laboratorist',
                'level' => 40,
                'module' => 'diagnostics',
                'description' => 'Laboratory and diagnostic reports, sample and test management.',
            ],
            [
                'name' => 'Accountant',
                'slug' => 'accountant',
                'level' => 45,
                'module' => 'finance',
                'description' => 'Billing, payments, bills and financial records.',
            ],
            [
                'name' => 'Store Keeper',
                'slug' => 'storekeeper',
                'level' => 50,
                'module' => 'inventory',
                'description' => 'Medical supplies and equipment inventory, purchase and issue.',
            ],
            [
                'name' => 'HR Manager',
                'slug' => 'hr',
                'level' => 50,
                'module' => 'hr',
                'description' => 'Employee records, departments, designations and staffing.',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['slug' => $role['slug']], $role);
        }
    }
}