<?php

namespace Tests\Feature;

use App\Http\Livewire\SuperAdmin\Themes as ThemesPage;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Theme;
use App\Models\ThemePayment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The platform sells public website designs: a theme is applied to a
 * facility's public site only after the super admin records its payment, and
 * revoking that payment puts the facility back on the default design.
 */
class WebsiteThemeTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $alpha;

    protected Tenant $beta;

    protected User $super;

    protected User $alphaAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(ThemeSeeder::class);

        $this->alpha = Tenant::create([
            'name' => 'Alpha Hospital', 'slug' => 'alpha', 'domain' => 'alpha.test',
            'mode' => 'hospital', 'status' => 'active',
        ]);

        $this->beta = Tenant::create([
            'name' => 'Beta Clinic', 'slug' => 'beta', 'domain' => 'beta.test',
            'mode' => 'clinic', 'status' => 'active',
        ]);

        $this->super = User::factory()->create([
            'role_id' => Role::where('slug', 'super_admin')->value('id'),
            'is_active' => true,
        ]);

        $this->alphaAdmin = User::factory()->create([
            'tenant_id' => $this->alpha->id,
            'role_id' => Role::where('slug', 'admin')->value('id'),
            'is_active' => true,
        ]);
    }

    protected function pay(string $key = 'aurora', ?Tenant $tenant = null): ThemePayment
    {
        $theme = Theme::where('key', $key)->firstOrFail();

        return ThemePayment::create([
            'tenant_id' => ($tenant ?? $this->alpha)->id,
            'theme_key' => $theme->key,
            'amount' => $theme->price,
            'currency' => $theme->currency,
            'method' => 'bank',
            'reference' => 'TX-'.strtoupper($theme->key),
            'paid_at' => now(),
            'unlocked_by' => $this->super->id,
        ]);
    }

    public function test_a_locked_theme_is_not_applied_to_the_public_site(): void
    {
        $this->alpha->forceFill(['theme_key' => 'aurora'])->save();

        $this->get('http://alpha.test/')
            ->assertOk()
            ->assertDontSee('themes/aurora/theme.css')
            ->assertSee('data-theme=""', false);
    }

    public function test_an_unlocked_theme_applies_to_that_facility_only(): void
    {
        $this->alpha->forceFill(['theme_key' => 'aurora'])->save();
        $this->pay('aurora');

        $this->get('http://alpha.test/')
            ->assertOk()
            ->assertSee('themes/aurora/theme.css')
            ->assertSee('themes/base.css')
            ->assertSee('data-theme="aurora"', false)
            ->assertSee('js/hms-reveal.js');

        $this->get('http://beta.test/')
            ->assertOk()
            ->assertDontSee('themes/aurora/theme.css')
            ->assertSee('data-theme=""', false);
    }

    public function test_revoking_the_payment_falls_back_to_the_default_design(): void
    {
        $this->alpha->forceFill(['theme_key' => 'aurora'])->save();
        $payment = $this->pay('aurora');

        $this->get('http://alpha.test/')->assertSee('themes/aurora/theme.css');

        $payment->forceFill(['revoked_at' => now()])->save();

        $this->get('http://alpha.test/')
            ->assertOk()
            ->assertDontSee('themes/aurora/theme.css');
    }

    public function test_the_super_admin_records_a_payment_and_activates_the_theme(): void
    {
        Livewire::actingAs($this->super)
            ->test(ThemesPage::class)
            ->assertSee('Aurora Care')
            ->set('tenantId', $this->alpha->id)
            ->set('themeKey', 'aurora')
            ->set('amount', '35000')
            ->set('method', 'bank')
            ->set('reference', 'TX-9001')
            ->call('unlock')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('theme_payments', [
            'tenant_id' => $this->alpha->id,
            'theme_key' => 'aurora',
            'amount' => 35000,
            'method' => 'bank',
            'reference' => 'TX-9001',
        ]);

        $this->assertSame('aurora', $this->alpha->fresh()->theme_key);

        $this->get('http://alpha.test/')->assertSee('themes/aurora/theme.css');
    }

    public function test_revoking_from_the_console_clears_the_facility_theme(): void
    {
        $this->alpha->forceFill(['theme_key' => 'aurora'])->save();
        $payment = $this->pay('aurora');

        Livewire::actingAs($this->super)
            ->test(ThemesPage::class)
            ->set('tenantId', $this->alpha->id)
            ->call('revoke', $payment->id);

        $this->assertNotNull($payment->fresh()->revoked_at);
        $this->assertNull($this->alpha->fresh()->theme_key);
    }

    public function test_the_super_admin_screen_renders(): void
    {
        $this->actingAs($this->super)
            ->get(route('superadmin.themes'))
            ->assertOk()
            ->assertSee('Website Themes')
            ->assertSee('Aurora Care');
    }

    public function test_the_theme_api_lists_the_catalogue_and_records_a_payment(): void
    {
        $response = $this->actingAs($this->super, 'sanctum')->getJson('/api/v1/superadmin/themes');

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertCount(10, $response->json('data'));

        $store = $this->actingAs($this->super, 'sanctum')->postJson('/api/v1/superadmin/theme-payments', [
            'tenant_id' => $this->beta->id,
            'theme_key' => 'royal',
            'amount' => 40000,
            'currency' => 'PKR',
            'method' => 'card',
            'reference' => 'API-77',
        ]);

        $store->assertCreated()->assertJsonPath('success', true);

        $this->assertSame('royal', $this->beta->fresh()->theme_key);

        $payments = $this->actingAs($this->super, 'sanctum')
            ->getJson('/api/v1/superadmin/theme-payments?tenant_id='.$this->beta->id);

        $payments->assertOk();
        $this->assertSame('API-77', $payments->json('data.0.reference'));

        $paymentId = $payments->json('data.0.id');

        $this->actingAs($this->super, 'sanctum')
            ->deleteJson('/api/v1/superadmin/theme-payments/'.$paymentId)
            ->assertOk();

        $this->assertNull($this->beta->fresh()->theme_key);
    }

    public function test_a_facility_admin_cannot_open_the_theme_screen(): void
    {
        $this->actingAs($this->alphaAdmin)
            ->get(route('superadmin.themes'))
            ->assertRedirect(route('superadmin.login'));
    }

    public function test_every_seeded_theme_ships_its_stylesheet(): void
    {
        $themes = Theme::orderBy('sort_order')->get();

        $this->assertCount(10, $themes);

        foreach ($themes as $theme) {
            $this->assertFileExists(public_path('themes/'.$theme->key.'/theme.css'));
        }
    }
}
