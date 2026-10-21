<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\Settings as SettingsPage;
use App\Models\feature;
use App\Models\Role;
use App\Models\Settings;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Each facility's public website reads its own settings, features and
 * testimonials. Rows saved without a tenant_id are platform defaults; a
 * facility overrides them from Website Content, and the request host decides
 * which facility's site is being served.
 */
class TenantWebsiteContentTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $alpha;

    protected Tenant $beta;

    protected User $alphaAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->seed(RoleSeeder::class);

        $this->alpha = $this->facility('Alpha Hospital', 'alpha-hospital', 'alpha.test');
        $this->beta = $this->facility('Beta Clinic', 'beta-clinic', 'beta.test');

        $this->alphaAdmin = $this->staff($this->alpha);

        Settings::create(['tenant_id' => null, 'key' => 'title', 'value' => 'Platform Default']);
        Settings::create(['tenant_id' => null, 'key' => 'address', 'value' => 'Metro Hospital Road, Kathmandu']);
    }

    protected function facility(string $name, string $slug, string $domain): Tenant
    {
        $tenant = Tenant::create([
            'name' => $name, 'slug' => $slug, 'domain' => $domain,
            'mode' => 'hospital', 'status' => 'active',
        ]);

        $tenant->syncRequiredRoles(['admin', 'doctor', 'nurse']);

        return $tenant;
    }

    protected function staff(Tenant $tenant): User
    {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role_id' => Role::where('slug', 'admin')->value('id'),
            'is_active' => true,
        ]);
    }

    public function test_each_facility_host_serves_its_own_branding(): void
    {
        Settings::create(['tenant_id' => $this->alpha->id, 'key' => 'title', 'value' => 'Alpha Website']);

        $this->get('http://alpha.test/')
            ->assertOk()
            ->assertSee('Alpha Website')
            ->assertDontSee('Platform Default')
            ->assertDontSee('Metro Hospital Road');

        // No override saved: the facility's own name is used, never Alpha's,
        // and a blank facility address never falls back to another hospital's.
        $this->get('http://beta.test/')
            ->assertOk()
            ->assertSee('Beta Clinic')
            ->assertDontSee('Alpha Website')
            ->assertDontSee('Metro Hospital Road');
    }

    public function test_features_and_testimonials_do_not_leak_between_facilities(): void
    {
        feature::create(['tenant_id' => $this->alpha->id, 'title' => 'Alpha Robotic Surgery', 'text' => 'Alpha only.', 'sort_order' => 1, 'active' => true]);
        feature::create(['tenant_id' => $this->beta->id, 'title' => 'Beta Dental Implant', 'text' => 'Beta only.', 'sort_order' => 1, 'active' => true]);

        $this->get('http://alpha.test/services')
            ->assertOk()
            ->assertSee('Alpha Robotic Surgery')
            ->assertDontSee('Beta Dental Implant');

        $this->get('http://beta.test/services')
            ->assertOk()
            ->assertSee('Beta Dental Implant')
            ->assertDontSee('Alpha Robotic Surgery');
    }

    public function test_the_settings_page_shows_the_signed_in_facility_not_the_global_default(): void
    {
        Livewire::actingAs($this->alphaAdmin)
            ->test(SettingsPage::class)
            ->assertSet('settings.title', 'Alpha Hospital');
    }

    public function test_a_facility_admin_saves_only_its_own_settings(): void
    {
        Livewire::actingAs($this->alphaAdmin)
            ->test(SettingsPage::class)
            ->set('settings.title', 'Alpha New Brand')
            ->call('updateSettings')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('settings', [
            'tenant_id' => $this->alpha->id, 'key' => 'title', 'value' => 'Alpha New Brand',
        ]);
        $this->assertDatabaseHas('settings', [
            'tenant_id' => null, 'key' => 'title', 'value' => 'Platform Default',
        ]);
        $this->assertDatabaseMissing('settings', [
            'tenant_id' => $this->beta->id, 'key' => 'title',
        ]);

        $this->get('http://alpha.test/')->assertSee('Alpha New Brand');
        $this->get('http://beta.test/')->assertSee('Beta Clinic');
    }
}
