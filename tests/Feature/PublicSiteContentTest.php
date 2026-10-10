<?php

namespace Tests\Feature;

use App\Models\feature;
use App\Models\Settings;
use App\Models\testimonial;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Everything on the public website is driven by the database: settings keys
 * feed every heading, label, pic and paragraph, features/testimonials come
 * from their own tables, and the mobile API mirrors the same content.
 */
class PublicSiteContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_adds_defaults_and_is_idempotent(): void
    {
        $this->seed(SiteContentSeeder::class);

        $this->assertDatabaseHas('settings', ['key' => 'testimonials_heading', 'value' => 'What Our Patients Say']);
        $this->assertDatabaseHas('settings', ['key' => 'hero_btn1', 'value' => 'Book Appointment']);

        $features = feature::count();
        $testimonials = testimonial::count();
        $this->assertSame(6, $features);
        $this->assertSame(2, $testimonials);

        $this->seed(SiteContentSeeder::class);

        $this->assertSame($features, feature::count());
        $this->assertSame($testimonials, testimonial::count());
    }

    public function test_homepage_headings_and_buttons_follow_settings(): void
    {
        foreach ([
            'hero_title' => 'Custom Hero Title',
            'hero_subtitle' => 'Custom hero text.',
            'hero_btn1' => 'Book Now Here',
            'hero_btn2' => 'See Team',
            'fact_working_title' => 'Clinic Hours',
            'fact_departments_title' => 'Divisions',
            'about_title' => 'About Our Institution',
            'about_btn' => 'Explore Services',
            'services_heading' => 'Treatments & Booking',
            'doctors_heading' => 'Meet Consultants',
            'testimonials_heading' => 'Patient Stories',
            'contact_heading' => 'Reach Us',
            'contact_card1_title' => 'Find Us',
            'contact_card2_title' => 'Phone & Mail',
            'contact_card3_title' => 'Opening Hours',
            'footer_contact_title' => 'Write To Us',
            'copyright_text' => 'Keep it true.',
        ] as $key => $value) {
            Settings::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Settings::updateOrCreate(['key' => 'description'], ['value' => 'A fully digital care story.']);

        $response = $this->get('/');

        $response->assertOk();

        foreach ([
            'Custom Hero Title',
            'Custom hero text.',
            'Book Now Here',
            'See Team',
            'Clinic Hours',
            'Divisions',
            'About Our Institution',
            'Explore Services',
            'Treatments &amp; Booking',
            'Meet Consultants',
            'Patient Stories',
            'Reach Us',
            'Find Us',
            'Phone &amp; Mail',
            'Opening Hours',
            'A fully digital care story.',
        ] as $needle) {
            $response->assertSee($needle, false);
        }

        $response
            ->assertSee('Write To Us', false)
            ->assertSee('Keep it true.', false);
    }

    public function test_homepage_testimonials_come_from_the_table(): void
    {
        testimonial::create([
            'name' => 'Ramesh Kumar',
            'role' => 'Orthopaedics Patient',
            'quote' => 'Clean wards, on-time reports. Highly recommended.',
            'sort_order' => 1,
            'active' => true,
        ]);

        $this->get('/')
            ->assertSee('Ramesh Kumar')
            ->assertSee('Clean wards, on-time reports. Highly recommended.')
            ->assertSee('What Our Patients Say'); // default heading before it is customised
    }

    public function test_inactive_testimonial_is_hidden_from_homepage(): void
    {
        testimonial::create([
            'name' => 'Hidden Case',
            'role' => 'Patient',
            'quote' => 'This should not render.',
            'sort_order' => 1,
            'active' => false,
        ]);

        $this->get('/')->assertDontSee('Hidden Case');
    }

    public function test_services_page_renders_features_from_the_table(): void
    {
        feature::create(['title' => 'Robotic Surgery', 'text' => 'DA Vinci robotic precision.', 'sort_order' => 1, 'active' => true]);
        feature::create(['title' => 'Air Ambulance', 'text' => 'Tracked transfers 24x7.', 'sort_order' => 2, 'active' => true]);

        $this->get('/services')
            ->assertOk()
            ->assertSee('Robotic Surgery')
            ->assertSee('DA Vinci robotic precision.')
            ->assertSee('Air Ambulance')
            ->assertSee('Our Services')
            ->assertDontSee('Lorem Ipsum');
    }

    public function test_about_page_uses_settings_headings_and_text(): void
    {
        Settings::updateOrCreate(['key' => 'about_heading'], ['value' => 'Who We Are']);
        Settings::updateOrCreate(['key' => 'about_sub_heading'], ['value' => 'Complete Care']);
        Settings::updateOrCreate(['key' => 'about_intro'], ['value' => 'A custom department line.']);
        Settings::updateOrCreate(['key' => 'about_title'], ['value' => 'About Sunshine Hospital']);
        Settings::updateOrCreate(['key' => 'description'], ['value' => 'Our story text.']);

        $this->get('/about')
            ->assertOk()
            ->assertSee('About Sunshine Hospital')
            ->assertSee('Who We Are')
            ->assertSee('Complete Care')
            ->assertSee('Our story text.')
            ->assertSee('A custom department line.');
    }

    public function test_doctors_page_uses_settings_heading_and_subtitle(): void
    {
        Settings::updateOrCreate(['key' => 'doctors_heading'], ['value' => 'Our Specialists']);
        Settings::updateOrCreate(['key' => 'doctors_subtitle'], ['value' => 'Detailed profiles from the panel.']);

        $this->get('/docters')
            ->assertOk()
            ->assertSee('Our Specialists')
            ->assertSee('Detailed profiles from the panel.');
    }

    public function test_api_site_exposes_features_and_testimonials(): void
    {
        feature::create(['title' => 'API Feature', 'text' => 'Shown to mobile clients.', 'sort_order' => 1, 'active' => true]);
        testimonial::create([
            'name' => 'API Patient',
            'role' => 'Patient',
            'quote' => 'Great app experience.',
            'sort_order' => 1,
            'active' => true,
        ]);

        $this->getJson('/api/v1/site')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.features.0.title', 'API Feature')
            ->assertJsonPath('data.testimonials.0.name', 'API Patient');
    }
}