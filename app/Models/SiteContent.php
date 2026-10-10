<?php

namespace App\Models;

use Illuminate\Support\Facades\Cache;

class SiteContent
{
    public static function get(): array
    {
        return Cache::rememberForever('site_content', function () {
            $s = [];

            foreach (Settings::all() as $row) {
                $s[$row->key] = $row->value;
            }

            return [
                'name' => $s['title'] ?? 'HMS',
                'tagline' => $s['tagline'] ?? 'Multi-Speciality Hospital',
                'mode' => hms_institution_mode(),
                'mode_label' => hms_institution_label(),
                'logo' => $s['logo'] ?? null,
                'icon' => $s['icon'] ?? null,
                'address' => $s['address'] ?? null,
                'phone' => $s['phone'] ?? ($s['business_phone'] ?? null),
                'email' => $s['email'] ?? ($s['business_email'] ?? null),
                'about' => $s['description'] ?? null,
                'about_title' => $s['about_title'] ?? null,
                'hero_title' => $s['hero_title'] ?? null,
                'hero_subtitle' => $s['hero_subtitle'] ?? null,
                'hero_image' => $s['hero_image'] ?? null,
                'emergency_title' => $s['emergency_title'] ?? null,
                'emergency_text' => $s['emergency_text'] ?? null,
                'working_hours' => $s['working_hours'] ?? ($s['working_horse'] ?? '7:00 AM - 8:00 PM'),
                'fact_working_title' => $s['fact_working_title'] ?? 'Working Hours',
                'fact_departments_title' => $s['fact_departments_title'] ?? 'Departments',
                'services_heading' => $s['services_heading'] ?? 'Services & Appointment',
                'services_page_heading' => $s['services_page_heading'] ?? 'Our Services',
                'doctors_heading' => $s['doctors_heading'] ?? 'Our Doctors',
                'doctors_subtitle' => $s['doctors_subtitle'] ?? null,
                'testimonials_heading' => $s['testimonials_heading'] ?? 'What Our Patients Say',
                'contact_heading' => $s['contact_heading'] ?? 'Get in Touch',
                'contact_card1_title' => $s['contact_card1_title'] ?? 'Visit Us',
                'contact_card2_title' => $s['contact_card2_title'] ?? 'Call / Email',
                'contact_card3_title' => $s['contact_card3_title'] ?? 'Working Hours',
                'about_heading' => $s['about_heading'] ?? 'What We Do',
                'about_sub_heading' => $s['about_sub_heading'] ?? 'Hospital Services',
                'about_intro' => $s['about_intro'] ?? null,
                'about_image' => $s['about_image'] ?? null,
                'hero_btn1' => $s['hero_btn1'] ?? 'Book Appointment',
                'hero_btn2' => $s['hero_btn2'] ?? 'Our Doctors',
                'about_btn' => $s['about_btn'] ?? 'Our Services',
                'footer_contact_title' => $s['footer_contact_title'] ?? 'Contact Us',
                'copyright_text' => $s['copyright_text'] ?? 'All rights reserved.',
                'facebook' => $s['facebook'] ?? '#',
                'twitter' => $s['twitter'] ?? '#',
                'instagram' => $s['instagram'] ?? '#',
                'linkedin' => $s['linkedin'] ?? '#',
                'youtube' => $s['youtube'] ?? '#',
                'pinterest' => $s['pinterest'] ?? '#',
            ];
        });
    }

    public static function flush(): void
    {
        Cache::forget('site_content');
    }

    public static function departments()
    {
        return department::with('hod')->orderBy('name')->get();
    }

    public static function doctors()
    {
        return employee::where('position', 'doctor')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    public static function medicines()
    {
        return medicine::whereNull('deleted_at')
            ->whereNotNull('name')
            ->orderBy('name')
            ->get();
    }

    public static function features()
    {
        return feature::where('active', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();
    }

    public static function testimonials()
    {
        return testimonial::where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}