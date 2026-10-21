<?php

namespace Database\Seeders;

use App\Models\Theme;
use Illuminate\Database\Seeder;

/**
 * Ten original website templates. Each one is a palette + typography + shape
 * layer over the existing public markup (see public/themes/{key}/theme.css).
 * Themes are premium by default: a facility only renders one after the platform
 * records its payment under Super Admin > Website Themes.
 */
class ThemeSeeder extends Seeder
{
    public function run(): void
    {
        $themes = [
            [
                'key' => 'aurora',
                'name' => 'Aurora Care',
                'description' => 'Teal glass panels, soft gradients and centred hero. Modern hospital feel.',
                'price' => 35000, 'primary_color' => '#0f766e', 'accent_color' => '#f59e0b',
                'dark_color' => '#083344', 'sort_order' => 1,
            ],
            [
                'key' => 'sunrise',
                'name' => 'Sunrise Clinic',
                'description' => 'Warm coral and cream with editorial serif headings. Friendly neighbourhood clinic.',
                'price' => 30000, 'primary_color' => '#ea580c', 'accent_color' => '#f59e0b',
                'dark_color' => '#7c2d12', 'sort_order' => 2,
            ],
            [
                'key' => 'emerald',
                'name' => 'Emerald Health',
                'description' => 'Clean emerald palette with airy white space and crisp cards.',
                'price' => 32000, 'primary_color' => '#059669', 'accent_color' => '#0f766e',
                'dark_color' => '#052e16', 'sort_order' => 3,
            ],
            [
                'key' => 'midnight',
                'name' => 'Midnight Ward',
                'description' => 'Dark premium interface with sky-blue glow accents. Great for night-time browsing.',
                'price' => 45000, 'primary_color' => '#38bdf8', 'accent_color' => '#facc15',
                'dark_color' => '#0b1120', 'sort_order' => 4,
            ],
            [
                'key' => 'coral',
                'name' => 'Coral Bloom',
                'description' => 'Rose and coral tones with oversized rounded cards and playful energy.',
                'price' => 28000, 'primary_color' => '#e11d48', 'accent_color' => '#fb7185',
                'dark_color' => '#4c0519', 'sort_order' => 5,
            ],
            [
                'key' => 'slate',
                'name' => 'Slate Minimal',
                'description' => 'Graphite and steel with sharp corners and a quiet, clinical layout.',
                'price' => 25000, 'primary_color' => '#475569', 'accent_color' => '#0ea5e9',
                'dark_color' => '#0f172a', 'sort_order' => 6,
            ],
            [
                'key' => 'royal',
                'name' => 'Royal Indigo',
                'description' => 'Indigo gradients with gold detailing and elevated premium cards.',
                'price' => 40000, 'primary_color' => '#6d28d9', 'accent_color' => '#f59e0b',
                'dark_color' => '#2e1065', 'sort_order' => 7,
            ],
            [
                'key' => 'forest',
                'name' => 'Forest Calm',
                'description' => 'Deep greens and warm parchment. Organic, reassuring and spacious.',
                'price' => 30000, 'primary_color' => '#15803d', 'accent_color' => '#ca8a04',
                'dark_color' => '#14261a', 'sort_order' => 8,
            ],
            [
                'key' => 'ocean',
                'name' => 'Ocean Breeze',
                'description' => 'Layered blues with cyan highlights and a smooth wave-style hero.',
                'price' => 34000, 'primary_color' => '#0284c7', 'accent_color' => '#06b6d4',
                'dark_color' => '#082f49', 'sort_order' => 9,
            ],
            [
                'key' => 'luxe',
                'name' => 'Luxe Medical',
                'description' => 'Black and champagne gold with serif headings. Concierge / luxury practice.',
                'price' => 60000, 'primary_color' => '#b45309', 'accent_color' => '#e7c873',
                'dark_color' => '#0c0a09', 'sort_order' => 10,
            ],
        ];

        foreach ($themes as $theme) {
            Theme::firstOrCreate(
                ['key' => $theme['key']],
                $theme + [
                    'currency' => 'PKR',
                    'is_premium' => true,
                    'is_active' => true,
                ]
            );
        }
    }
}
