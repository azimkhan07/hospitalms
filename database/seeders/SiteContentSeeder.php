<?php

namespace Database\Seeders;

use App\Http\Livewire\Admins\Settings;
use App\Models\feature;
use App\Models\testimonial;
use Illuminate\Database\Seeder;

/**
 * Public website content. Idempotent: re-running updates the same rows, the
 * same firstOrCreate'd setting defaults (kept in sync with the Settings panel)
 * and the baseline feature/testimonial cards.
 */
class SiteContentSeeder extends Seeder
{
    public function run(): void
    {
        foreach ((new Settings)->defaults as $key => $value) {
            \App\Models\Settings::firstOrCreate(['key' => $key], ['value' => $value]);
        }

        $features = [
            ['title' => '24x7 Emergency', 'text' => 'Round-the-clock trauma and emergency care with on-call consultants.', 'sort_order' => 1],
            ['title' => 'Experienced Specialists', 'text' => 'Senior consultants across every department, attached to one shared record.', 'sort_order' => 2],
            ['title' => 'Modern Diagnostics', 'text' => 'In-house imaging and laboratory services with fast same-day reports.', 'sort_order' => 3],
            ['title' => 'In-house Pharmacy', 'text' => 'Prescriptions filled on the spot from our fully-stocked pharmacy.', 'sort_order' => 4],
            ['title' => 'In-patient Care', 'text' => 'Comfortable rooms, nursing care and daily consultant rounds.', 'sort_order' => 5],
            ['title' => 'Digital Records', 'text' => 'Every admission, test and prescription on one secure digital file.', 'sort_order' => 6],
        ];

        foreach ($features as $attrs) {
            feature::updateOrCreate(['title' => $attrs['title']], $attrs + ['icon' => null, 'active' => true]);
        }

        $testimonials = [
            [
                'name' => 'Amit Verma',
                'role' => 'Cardiology Patient',
                'quote' => 'Coordinated diagnosis, careful treatment and clear follow-up. Everything stayed on one file across departments.',
                'sort_order' => 1,
            ],
            [
                'name' => 'Sneha Kapoor',
                'role' => 'Surgery Patient',
                'quote' => 'The staff explained every step, and the follow-up calls after discharge made all the difference.',
                'sort_order' => 2,
            ],
        ];

        foreach ($testimonials as $attrs) {
            testimonial::updateOrCreate(['name' => $attrs['name']], $attrs + ['photo' => null, 'active' => true]);
        }

        \App\Models\SiteContent::flush();
    }
}