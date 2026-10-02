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
}