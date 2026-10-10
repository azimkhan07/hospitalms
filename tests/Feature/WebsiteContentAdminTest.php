<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\Features;
use App\Http\Livewire\Admins\Testimonials;
use App\Models\feature;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\testimonial;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Website Content admin pages (Features + Testimonials) are tenant-staff
 * screens gated behind the "settings" permission, and their rows drive the
 * public site and the API.
 */
class WebsiteContentAdminTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Web Hospital', 'slug' => 'web-hospital',
            'mode' => 'hospital', 'status' => 'active', 'geo_radius_meters' => 200,
        ]);
        $this->tenant->syncRequiredRoles([
            'admin', 'doctor', 'nurse',
        ]);

        $this->admin = $this->staff('admin');
    }

    protected function staff(string $slug): User
    {
        return User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('slug', $slug)->value('id'),
            'is_active' => true,
        ]);
    }

    public function test_features_page_renders_for_admin(): void
    {
        feature::create(['title' => 'Smart ICU', 'text' => 'Monitored beds.', 'sort_order' => 1, 'active' => true]);

        $this->actingAs($this->admin)
            ->get(route('admin_features'))
            ->assertOk()
            ->assertSee('Smart ICU')
            ->assertSee('Website Features');
    }

    public function test_features_page_forbidden_without_settings_permission(): void
    {
        $this->actingAs($this->staff('nurse'))
            ->get(route('admin_features'))
            ->assertForbidden();
    }

    public function test_admin_creates_and_deletes_feature(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Features::class)
            ->call('show_create_form')
            ->set('title', 'Robotic Surgery')
            ->set('text', 'DA Vinci precision.')
            ->set('sort_order', 3)
            ->call('store')
            ->assertHasNoErrors()
            ->assertSet('_page', 'index');

        $this->assertDatabaseHas('features', ['title' => 'Robotic Surgery', 'active' => true]);

        $id = feature::where('title', 'Robotic Surgery')->value('id');

        Livewire::actingAs($this->admin)
            ->test(Features::class)
            ->call('toggle', $id);

        $this->assertDatabaseHas('features', ['id' => $id, 'active' => false]);

        Livewire::actingAs($this->admin)
            ->test(Features::class)
            ->call('delete', $id);

        $this->assertDatabaseMissing('features', ['id' => $id]);
    }

    public function test_testimonials_page_renders_and_forbids_doctor(): void
    {
        testimonial::create([
            'name' => 'Meena Sharma', 'role' => 'Patient', 'quote' => 'Excellent care.',
            'sort_order' => 1, 'active' => true,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin_testimonials'))
            ->assertOk()
            ->assertSee('Meena Sharma')
            ->assertSee('Patient Testimonials');

        $this->actingAs($this->staff('doctor'))
            ->get(route('admin_testimonials'))
            ->assertForbidden();
    }

    public function test_admin_creates_testimonial_and_it_reaches_homepage(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Testimonials::class)
            ->call('show_create_form')
            ->set('name', 'Asif Iqbal')
            ->set('role', 'Cardiology Patient')
            ->set('quote', 'Five star follow-up experience.')
            ->call('store')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('testimonials', ['name' => 'Asif Iqbal', 'active' => true]);

        $this->get('/')
            ->assertSee('Asif Iqbal')
            ->assertSee('Five star follow-up experience.');
    }

    public function test_new_routes_are_mounted_in_the_admin_sidebar(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin_dashboard'))
            ->assertOk()
            ->assertSee('Website Content')
            ->assertSee('Features');
    }
}