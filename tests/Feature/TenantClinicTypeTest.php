<?php

namespace Tests\Feature;

use App\Http\Livewire\SuperAdmin\TenantForm;
use App\Models\ClinicType;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TenantClinicTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The wizard validates against real rows, so the fixture needs the roles
        // and the clinic types the migration seeds.
        $this->seed(\Database\Seeders\RoleSeeder::class);

        foreach ([
            ['general', 'General', 'both'],
            ['multispeciality', 'Multispeciality', 'both'],
            ['dental', 'Dental', 'both'],
            ['skin', 'Skin / Dermatology', 'both'],
            ['oncology', 'Oncology', 'hospital'],
        ] as [$slug, $name, $applies]) {
            ClinicType::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'applies_to' => $applies, 'is_active' => true, 'sort_order' => 1]
            );
        }
    }

    protected function platform(): User
    {
        return User::factory()->create([
            'role_id' => \App\Models\Role::where('slug', 'super_admin')->value('id'),
            'tenant_id' => null,
        ]);
    }

    public function test_a_clinic_must_be_given_a_type(): void
    {
        $this->actingAs($this->platform());

        Livewire::test(TenantForm::class)
            ->call('open')
            ->set('create_admin', false)
            ->set('name', 'No Type Clinic')
            ->set('slug', 'no-type-clinic')
            ->set('mode', 'clinic')
            ->set('clinic_type_id', '')
            ->set('requiredRoles', ['admin'])
            ->call('save')
            ->assertHasErrors('clinic_type_id');
    }

    public function test_at_least_one_role_is_required(): void
    {
        $this->actingAs($this->platform());

        Livewire::test(TenantForm::class)
            ->call('open')
            ->set('create_admin', false)
            ->set('name', 'Empty Roles')
            ->set('slug', 'empty-roles')
            ->set('mode', 'clinic')
            ->set('clinic_type_id', ClinicType::where('slug', 'dental')->value('id'))
            ->set('requiredRoles', [])
            ->call('save')
            ->assertHasErrors('requiredRoles');
    }

    public function test_super_admin_can_never_be_ticked(): void
    {
        $this->actingAs($this->platform());

        Livewire::test(TenantForm::class)
            ->call('open')
            ->set('create_admin', false)
            ->set('name', 'Sneaky Clinic')
            ->set('slug', 'sneaky-clinic')
            ->set('mode', 'clinic')
            ->set('clinic_type_id', ClinicType::where('slug', 'dental')->value('id'))
            ->set('requiredRoles', ['admin', 'doctor', 'super_admin'])
            ->call('save')
            ->assertHasErrors('requiredRoles.2');

        // nothing may be written when validation failed
        $this->assertNull(Tenant::where('slug', 'sneaky-clinic')->first());
    }

    public function test_a_clinic_cannot_be_given_a_role_its_mode_forbids(): void
    {
        $this->actingAs($this->platform());

        // nurse is a hospital role; tick it anyway and it must not be stored.
        Livewire::test(TenantForm::class)
            ->call('open')
            ->set('create_admin', false)
            ->set('name', 'Clinic With A Nurse')
            ->set('slug', 'clinic-with-a-nurse')
            ->set('mode', 'clinic')
            ->set('clinic_type_id', ClinicType::where('slug', 'dental')->value('id'))
            ->set('requiredRoles', ['admin', 'doctor', 'nurse', 'moderator'])
            ->call('save')
            // a clinic cannot be given a nurse or a Dean, and says so
            ->assertHasErrors(['requiredRoles.2', 'requiredRoles.3']);

        $this->assertNull(Tenant::where('slug', 'clinic-with-a-nurse')->first());
    }

    public function test_choosing_a_type_suggests_its_roles(): void
    {
        $this->actingAs($this->platform());

        Livewire::test(TenantForm::class)
            ->call('open')
            ->set('create_admin', false)
            ->set('mode', 'clinic')
            ->set('clinic_type_id', ClinicType::where('slug', 'dental')->value('id'))
            ->assertSet('requiredRoles', ['admin', 'receptionist', 'doctor']);
    }

    public function test_a_hand_picked_role_list_survives_a_type_change(): void
    {
        $this->actingAs($this->platform());

        Livewire::test(TenantForm::class)
            ->call('open')
            ->set('create_admin', false)
            ->set('mode', 'clinic')
            ->set('clinic_type_id', ClinicType::where('slug', 'general')->value('id'))
            ->set('requiredRoles', ['admin', 'doctor'])
            ->set('clinic_type_id', ClinicType::where('slug', 'dental')->value('id'))
            ->assertSet('requiredRoles', ['admin', 'doctor']);
    }

    public function test_a_hospital_only_type_is_not_offered_to_a_clinic(): void
    {
        $this->actingAs($this->platform());

        $this->assertFalse(
            ClinicType::active()->forMode('clinic')->where('slug', 'oncology')->exists()
        );

        Livewire::test(TenantForm::class)
            ->call('open')
            ->set('create_admin', false)
            ->set('mode', 'clinic')
            ->assertSet('clinic_type_id', '');
    }

    public function test_a_clinic_never_keeps_the_private_room_flag(): void
    {
        $this->actingAs($this->platform());

        Livewire::test(TenantForm::class)
            ->call('open')
            ->set('create_admin', false)
            ->set('name', 'Clinic With A Leftover Flag')
            ->set('slug', 'clinic-leftover-flag')
            ->set('mode', 'clinic')
            ->set('clinic_type_id', ClinicType::where('slug', 'dental')->value('id'))
            ->set('requiredRoles', ['admin', 'doctor'])
            // forged payload: private rooms are a hospital concept
            ->set('private_room_enabled', true)
            ->set('private_room_count', 5)
            ->call('save')
            ->assertHasNoErrors();

        $tenant = Tenant::where('slug', 'clinic-leftover-flag')->firstOrFail();

        $this->assertFalse((bool) $tenant->private_room_enabled);
        $this->assertNull($tenant->private_room_count);
    }

    public function test_the_saved_roles_drive_the_role_gate(): void
    {
        $this->actingAs($this->platform());

        $tenantId = null;

        Livewire::test(TenantForm::class)
            ->call('open')
            ->set('create_admin', false)
            ->set('name', 'Skin Clinic')
            ->set('slug', 'skin-clinic')
            ->set('mode', 'clinic')
            ->set('clinic_type_id', ClinicType::where('slug', 'skin')->value('id'))
            ->set('requiredRoles', ['admin', 'receptionist', 'doctor'])
            ->call('save')
            ->assertHasNoErrors();

        $tenant = Tenant::where('slug', 'skin-clinic')->firstOrFail();
        $tenantId = $tenant->id;

        $this->assertTrue($tenant->hasRole('doctor'));
        $this->assertFalse($tenant->hasRole('pharmacist'));

        // A signed-in staff member of a non-ticked role is refused by the gate.
        $reception = User::factory()->create([
            'tenant_id' => $tenantId,
            'role_id' => \App\Models\Role::where('slug', 'pharmacist')->value('id'),
        ]);

        $this->assertFalse(hms_role_enabled('pharmacist', null, $reception));
    }

    public function test_editing_loads_the_stored_type_and_roles(): void
    {
        $this->actingAs($this->platform());

        $tenant = Tenant::create([
            'name' => 'Round Trip', 'slug' => 'round-trip', 'mode' => 'clinic',
            'status' => 'active', 'geo_radius_meters' => 200,
            'clinic_type_id' => ClinicType::where('slug', 'ent')->value('id'),
        ]);
        $tenant->syncRequiredRoles(['admin', 'receptionist', 'doctor']);

        Livewire::test(TenantForm::class)
            ->call('open', $tenant->id)
            ->assertSet('clinic_type_id', (string) $tenant->clinic_type_id)
            ->assertSet('requiredRoles', ['admin', 'doctor', 'receptionist']);
    }

    public function test_the_role_boxes_offered_on_step_two_follow_the_mode(): void
    {
        $this->actingAs($this->platform());

        $dental = ClinicType::where('slug', 'dental')->value('id');

        // A clinic only ever offers the roles its mode allows, and never
        // super_admin, even though the platform holds that role itself.
        Livewire::test(TenantForm::class)
            ->call('open')
            ->set('create_admin', false)
            ->set('name', 'Role Box Clinic')
            ->set('slug', 'role-box-clinic')
            ->set('mode', 'clinic')
            ->set('clinic_type_id', $dental)
            ->call('next')
            ->assertSet('step', 2)
            ->assertViewHas('modeRoleSlugs', function (array $slugs): bool {
                $this->assertSame(['admin', 'receptionist', 'doctor', 'pharmacist'], $slugs);

                return true;
            })
            ->assertViewHas('requiredRoles', function (array $roles): bool {
                sort($roles);
                $this->assertSame(['admin', 'doctor', 'receptionist'], $roles);

                return true;
            });

        // A hospital is the wide mode: every sellable role is on offer.
        Livewire::test(TenantForm::class)
            ->call('open')
            ->set('create_admin', false)
            ->set('name', 'Role Box Hospital')
            ->set('slug', 'role-box-hospital')
            ->set('mode', 'hospital')
            ->set('clinic_type_id', ClinicType::where('slug', 'multispeciality')->value('id'))
            ->call('next')
            ->assertSet('step', 2)
            ->assertViewHas('modeRoleSlugs', function (array $slugs): bool {
                $this->assertContains('nurse', $slugs);
                $this->assertContains('moderator', $slugs);
                $this->assertNotContains('super_admin', $slugs);
                $this->assertCount(10, $slugs);

                return true;
            });
    }

    public function test_step_one_can_be_left_before_the_role_list_is_ticked(): void
    {
        $this->actingAs($this->platform());

        // The role boxes live on step two, so step one must not demand them --
        // otherwise the wizard can never reach the boxes it is asking about.
        Livewire::test(TenantForm::class)
            ->call('open')
            ->set('create_admin', false)
            ->set('name', 'Deadlock Check')
            ->set('slug', 'deadlock-check')
            ->set('mode', 'clinic')
            ->set('clinic_type_id', ClinicType::where('slug', 'dental')->value('id'))
            ->set('requiredRoles', [])
            ->call('next')
            ->assertSet('step', 2)
            ->assertHasNoErrors();
    }

    public function test_auto_mode_generates_a_subdomain_under_the_base_domain(): void
    {
        config(['hms.base_domain' => 'hms.test']);
        $this->actingAs($this->platform());

        Livewire::test(TenantForm::class)
            ->call('open')
            ->set('create_admin', false)
            ->set('name', 'Auto Address Clinic')
            ->set('slug', 'auto-address-clinic')
            ->set('domain_mode', 'auto')
            ->set('custom_domain', '')
            ->set('mode', 'clinic')
            ->set('clinic_type_id', ClinicType::where('slug', 'dental')->value('id'))
            ->set('requiredRoles', ['admin', 'doctor'])
            ->call('save')
            ->assertHasNoErrors();

        $tenant = Tenant::where('slug', 'auto-address-clinic')->firstOrFail();

        // The platform owns the address: {slug}.{base_domain}, never a typed one.
        $this->assertSame('auto-address-clinic.hms.test', $tenant->subdomain);
        $this->assertNull($tenant->domain);
    }

    public function test_auto_subdomain_is_previewed_from_the_slug(): void
    {
        config(['hms.base_domain' => 'hms.test']);
        $this->actingAs($this->platform());

        Livewire::test(TenantForm::class)
            ->call('open')
            ->set('create_admin', false)
            ->set('name', 'Preview Clinic')
            ->set('slug', 'preview-clinic')
            ->assertViewHas('autoSubdomain', fn ($value) => $value === 'preview-clinic.hms.test');
    }

    public function test_custom_mode_stores_the_typed_domain_and_no_subdomain(): void
    {
        $this->actingAs($this->platform());

        Livewire::test(TenantForm::class)
            ->call('open')
            ->set('create_admin', false)
            ->set('name', 'Own Domain Clinic')
            ->set('slug', 'own-domain-clinic')
            ->set('domain_mode', 'custom')
            ->set('custom_domain', 'Clinic.Example.com')
            ->set('mode', 'clinic')
            ->set('clinic_type_id', ClinicType::where('slug', 'dental')->value('id'))
            ->set('requiredRoles', ['admin', 'doctor'])
            ->call('save')
            ->assertHasNoErrors();

        $tenant = Tenant::where('slug', 'own-domain-clinic')->firstOrFail();

        $this->assertSame('clinic.example.com', $tenant->domain);
        $this->assertNull($tenant->subdomain);
    }

    public function test_custom_mode_demands_a_domain(): void
    {
        $this->actingAs($this->platform());

        Livewire::test(TenantForm::class)
            ->call('open')
            ->set('create_admin', false)
            ->set('name', 'No Domain Clinic')
            ->set('slug', 'no-domain-clinic')
            ->set('domain_mode', 'custom')
            ->set('custom_domain', '')
            ->set('mode', 'clinic')
            ->set('clinic_type_id', ClinicType::where('slug', 'dental')->value('id'))
            ->set('requiredRoles', ['admin', 'doctor'])
            ->call('save')
            ->assertHasErrors('custom_domain');

        $this->assertNull(Tenant::where('slug', 'no-domain-clinic')->first());
    }

    public function test_a_custom_domain_cannot_be_claimed_twice(): void
    {
        $this->actingAs($this->platform());

        Tenant::create([
            'name' => 'First Owner', 'slug' => 'first-owner', 'mode' => 'clinic',
            'status' => 'active', 'domain' => 'taken.example.com',
        ]);

        Livewire::test(TenantForm::class)
            ->call('open')
            ->set('create_admin', false)
            ->set('name', 'Second Owner')
            ->set('slug', 'second-owner')
            ->set('domain_mode', 'custom')
            ->set('custom_domain', 'taken.example.com')
            ->set('mode', 'clinic')
            ->set('clinic_type_id', ClinicType::where('slug', 'dental')->value('id'))
            ->set('requiredRoles', ['admin', 'doctor'])
            ->call('save')
            ->assertHasErrors('custom_domain');

        $this->assertNull(Tenant::where('slug', 'second-owner')->first());
    }

    public function test_editing_a_custom_domain_tenant_reopens_in_custom_mode(): void
    {
        $this->actingAs($this->platform());

        $tenant = Tenant::create([
            'name' => 'Load Domain', 'slug' => 'load-domain', 'mode' => 'clinic',
            'status' => 'active', 'domain' => 'load.example.com',
        ]);

        Livewire::test(TenantForm::class)
            ->call('open', $tenant->id)
            ->assertSet('domain_mode', 'custom')
            ->assertSet('custom_domain', 'load.example.com');
    }
}