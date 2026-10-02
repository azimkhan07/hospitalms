<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Seeds the roles, the hospital/clinic settings the public site reads,
     * the super admin and the demo staff accounts for every role.
     *
     * @return void
     */
    public function run()
    {
        $this->call(RoleSeeder::class);

        $settings = [
            'title' => 'Metro Multi-Speciality Hospital',
            'tagline' => 'Multi-Speciality Hospital',
            'institution_mode' => 'hospital',
            'description' => 'A 350-bed multi-speciality hospital offering tertiary care across cardiology, neurology, orthopaedics, oncology and critical care. Round-the-clock emergency services with a team of 180 consultants and 640 nursing staff.',
            'about_title' => 'About Us',
            'hero_title' => 'Compassionate care, advanced medicine',
            'hero_subtitle' => 'Book an appointment online or walk in — our specialists are here for you.',
            'emergency_title' => 'Emergency 24x7',
            'emergency_text' => 'Round-the-clock trauma and emergency care with on-call consultants.',
            'working_hours' => 'OPD 8:00 AM - 8:00 PM',
            'address' => '14 Civil Line, Near Metro Hospital Road, Kathmandu',
            'phone' => '+977-1-5550199',
            'email' => 'info@metrohospital.com',
            'business_phone' => '+977-1-5550199',
            'business_email' => 'info@metrohospital.com',
            'logo' => 'logo.png',
            'icon' => 'icon.png',
            'facebook' => '#',
            'twitter' => '#',
            'instagram' => '#',
            'linkedin' => '#',
            'youtube' => '#',
            'pinterest' => '#',
        ];

        foreach ($settings as $key => $value) {
            Settings::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $roles = Role::pluck('id', 'slug');

        $tenant = \App\Models\Tenant::firstOrCreate(
            ['slug' => 'metro-hospital'],
            [
                'name' => 'Metro Multi-Speciality Hospital',
                'mode' => Settings::where('key', 'institution_mode')->value('value') ?: 'hospital',
                'phone' => $settings['phone'] ?? null,
                'email' => $settings['email'] ?? null,
                'address' => $settings['address'] ?? null,
                'working_hours' => $settings['working_hours'] ?? null,
                'logo' => $settings['logo'] ?? null,
                'status' => 'active',
                'facilities' => ['beds' => 350, 'staff' => 820, 'has_lab' => true, 'has_ot' => true, 'has_ambulance' => true],
            ]
        );

        if (! empty($roles['super_admin'])) {
            User::updateOrCreate(
                ['email' => 'super@hms.com'],
                [
                    'name' => 'Platform Super Admin',
                    'password' => bcrypt('123456'),
                    'role_id' => $roles['super_admin'],
                    'designation' => 'Super Admin',
                    'department' => 'Platform',
                    'is_active' => true,
                    'tenant_id' => null,
                ]
            );
        }

        $users = [
            ['name' => 'Azim Khan', 'email' => 'azim@hms.com', 'role' => 'admin', 'designation' => 'Hospital Admin', 'department' => 'Administration'],
            ['name' => 'Dean Moderator', 'email' => 'mod@hms.com', 'role' => 'moderator', 'designation' => 'Dean', 'department' => 'Administration'],
            ['name' => 'Doctor Demo', 'email' => 'doc@hms.com', 'role' => 'doctor', 'designation' => 'Consultant', 'department' => 'General Medicine'],
            ['name' => 'Nurse Demo', 'email' => 'nurse@hms.com', 'role' => 'nurse', 'designation' => 'Staff Nurse', 'department' => 'Nursing'],
            ['name' => 'Reception Demo', 'email' => 'recep@hms.com', 'role' => 'receptionist', 'designation' => 'Receptionist', 'department' => 'Front Desk'],
            ['name' => 'Pharmacist Demo', 'email' => 'pharma@hms.com', 'role' => 'pharmacist', 'designation' => 'Pharmacist', 'department' => 'Pharmacy'],
            ['name' => 'Laboratorist Demo', 'email' => 'lab@hms.com', 'role' => 'laboratorist', 'designation' => 'Lab Technologist', 'department' => 'Diagnostics'],
            ['name' => 'Store Keeper Demo', 'email' => 'store@hms.com', 'role' => 'storekeeper', 'designation' => 'Store Keeper', 'department' => 'Inventory'],
            ['name' => 'HR Manager Demo', 'email' => 'hr@hms.com', 'role' => 'hr', 'designation' => 'HR Manager', 'department' => 'Human Resource'],
            ['name' => 'Accountant Demo', 'email' => 'accountant@gmail.com', 'role' => 'accountant', 'designation' => 'Accountant', 'department' => 'Finance'],
        ];

        foreach ($users as $row) {
            if (empty($roles[$row['role']])) {
                continue;
            }

            User::updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'password' => bcrypt('123456'),
                    'role_id' => $roles[$row['role']],
                    'designation' => $row['designation'],
                    'department' => $row['department'],
                    'is_active' => true,
                    'tenant_id' => $tenant->id,
                ]
            );
        }
    }
}
