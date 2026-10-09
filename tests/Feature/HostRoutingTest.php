<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Multi-tenant host routing: the super admin panel is locked to the operator's
 * host(s), the facility panel and staff login stay off the platform host, and a
 * facility with a purchased domain is redirected from its generated address.
 *
 * With HMS_PLATFORM_HOSTS empty (the default) the lock is disabled, so local
 * development and single-host installs are unaffected.
 */
class HostRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    protected function facility(array $overrides = []): Tenant
    {
        return Tenant::create(array_merge([
            'name' => 'Care Clinic', 'slug' => 'care', 'mode' => 'clinic', 'status' => 'active',
        ], $overrides));
    }

    public function test_the_platform_login_is_served_only_from_the_platform_host(): void
    {
        config([
            'hms.platform_hosts' => ['hms.test'],
            'hms.base_domain' => 'hms.test',
        ]);

        $this->get('http://hms.test/admin')->assertOk();

        // A facility's host must not serve the operator's login.
        $this->get('http://care.hms.test/admin')
            ->assertRedirect('http://hms.test/admin');
    }

    public function test_the_superadmin_panel_is_not_served_from_a_facility_host(): void
    {
        config(['hms.platform_hosts' => ['hms.test']]);

        $this->get('http://care.hms.test/superadmin/dashboard')
            ->assertRedirect('http://hms.test/admin');
    }

    public function test_the_facility_panel_is_not_served_from_the_platform_host(): void
    {
        config(['hms.platform_hosts' => ['hms.test']]);

        $this->get('http://hms.test/admin/dashboard')->assertNotFound();
        $this->get('http://hms.test/login')->assertNotFound();
    }

    public function test_a_facility_panel_still_redirects_to_login_on_its_own_host(): void
    {
        config(['hms.platform_hosts' => ['hms.test']]);

        $this->get('http://care.hms.test/admin/dashboard')->assertRedirect();
    }

    public function test_a_facility_with_a_custom_domain_redirects_from_its_generated_address(): void
    {
        config(['hms.platform_hosts' => []]);

        $this->facility([
            'subdomain' => 'care.hms.test',
            'domain' => 'care.example.com',
        ]);

        $this->get('http://care.hms.test/')
            ->assertStatus(301)
            ->assertRedirect('http://care.example.com/');
    }

    public function test_the_custom_domain_itself_is_not_redirected(): void
    {
        config(['hms.platform_hosts' => []]);

        $this->facility([
            'subdomain' => 'care.hms.test',
            'domain' => 'care.example.com',
        ]);

        // The canonical host serves the facility; it must not bounce.
        $this->assertNotSame(301, $this->get('http://care.example.com/')->getStatusCode());
    }

    public function test_the_host_lock_is_disabled_when_no_platform_hosts_are_configured(): void
    {
        config(['hms.platform_hosts' => []]);

        // Any host may reach the login, and the facility panel falls through to
        // the auth redirect rather than a 404.
        $this->get('http://anything.test/admin')->assertOk();
        $this->get('http://anything.test/admin/dashboard')->assertRedirect();
    }
}
