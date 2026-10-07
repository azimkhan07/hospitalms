<?php

namespace Tests\Feature;

use App\Http\Middleware\ResolveTenant;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Phase 11: ResolveTenant resolves the active facility from the request host
 * for branding/scoping, keyed by the configured host_map or by tenants.domain.
 */
class TenantResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
        Role::where('slug', 'super_admin')->firstOrFail();
    }

    protected function tenant(string $slug, array $overrides = []): Tenant
    {
        return Tenant::create(array_merge([
            'name' => ucfirst($slug), 'slug' => $slug,
            'mode' => 'clinic', 'status' => 'active',
        ], $overrides));
    }

    protected function resolvedTenantId(string $url): ?int
    {
        $seen = 'unset';
        $request = Request::create($url);

        (new ResolveTenant)->handle($request, function () use (&$seen) {
            $seen = hms_tenant()?->id;

            return response('ok');
        });

        return $seen === 'unset' ? null : $seen;
    }

    public function test_host_map_resolves_the_mapped_tenant(): void
    {
        $this->tenant('first-clinic');
        $second = $this->tenant('second-clinic');

        config(['hms.host_map' => ['maps.test' => $second->id]]);

        $this->assertSame($second->id, $this->resolvedTenantId('http://maps.test/ping'));
    }

    public function test_an_unknown_host_resolves_to_nothing(): void
    {
        $this->assertNull($this->resolvedTenantId('http://nowhere.example/ping'));
    }

    public function test_the_tenants_domain_resolves_the_tenant(): void
    {
        $facility = $this->tenant('domain-clinic', ['domain' => 'domain-clinic.example']);

        $this->assertSame($facility->id, $this->resolvedTenantId('http://domain-clinic.example/ping'));
    }

    public function test_inactive_tenants_do_not_resolve(): void
    {
        $inactive = $this->tenant('inactive-clinic', ['status' => 'inactive']);

        config(['hms.host_map' => ['dead.test' => $inactive->id]]);

        $this->assertNull($this->resolvedTenantId('http://dead.test/ping'));
    }
}