<?php

namespace Tests\Feature;

use App\Http\Livewire\Admins\StaffDirectory;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The facility's owner ticks the roles it needs; the directory must hire inside
 * that list only, and both the tenant admin and the Dean (moderator) may do so.
 */
class StaffRoleCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    protected function roleId(string $slug): int
    {
        return Role::where('slug', $slug)->value('id');
    }

    /** A hospital set up for everything except labouratorist. */
    protected function hospital(): Tenant
    {
        $tenant = Tenant::create([
            'name' => 'Staffing Hospital', 'slug' => 'staffing-hospital',
            'mode' => 'hospital', 'status' => 'active', 'geo_radius_meters' => 200,
        ]);

        $tenant->syncRequiredRoles([
            'admin', 'moderator', 'doctor', 'nurse', 'receptionist', 'pharmacist',
        ]);

        return $tenant;
    }

    protected function staff(Tenant $tenant, string $slug): User
    {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $this->roleId($slug),
            'is_active' => true,
        ]);
    }

    public function test_the_directory_offers_exactly_the_ticked_roles(): void
    {
        $tenant = $this->hospital();
        $this->actingAs($this->staff($tenant, 'admin'));

        $slugs = Livewire::test(StaffDirectory::class)
            ->instance()
            ->assignableRoles()
            ->pluck('slug')
            ->all();

        $this->assertEqualsCanonicalizing(
            ['admin', 'moderator', 'doctor', 'nurse', 'receptionist', 'pharmacist'],
            $slugs
        );

        // Laboratorist exists in the platform but this facility never asked for it.
        $this->assertNotContains('laboratorist', $slugs);

        // And the platform's own role is never offerable from inside a facility.
        $this->assertNotContains('super_admin', $slugs);
    }

    public function test_the_admin_can_hire_any_ticked_role(): void
    {
        $tenant = $this->hospital();
        $this->actingAs($this->staff($tenant, 'admin'));

        Livewire::test(StaffDirectory::class)
            ->call('createStaff')
            ->set('name', 'New Nurse')
            ->set('email', 'new.nurse@hospital.test')
            ->set('newRole', 'nurse')
            ->set('password', 'secret123')
            ->set('password_confirmation', 'secret123')
            ->call('saveStaff')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'new.nurse@hospital.test',
            'tenant_id' => $tenant->id,
            'role_id' => $this->roleId('nurse'),
        ]);
    }

    public function test_the_dean_can_hire_a_ticked_role(): void
    {
        $tenant = $this->hospital();
        $this->actingAs($this->staff($tenant, 'moderator'));

        Livewire::test(StaffDirectory::class)
            ->call('createStaff')
            ->set('name', 'Dean Hired Doctor')
            ->set('email', 'dean.doctor@hospital.test')
            ->set('newRole', 'doctor')
            ->set('password', 'secret123')
            ->set('password_confirmation', 'secret123')
            ->call('saveStaff')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'dean.doctor@hospital.test',
            'role_id' => $this->roleId('doctor'),
        ]);
    }

    public function test_an_unticked_role_is_refused_even_from_a_hand_built_form(): void
    {
        $tenant = $this->hospital();
        $this->actingAs($this->staff($tenant, 'admin'));

        Livewire::test(StaffDirectory::class)
            ->call('createStaff')
            ->set('name', 'Sneaky Laboratorist')
            ->set('email', 'sneaky.lab@hospital.test')
            ->set('newRole', 'laboratorist')
            ->set('password', 'secret123')
            ->set('password_confirmation', 'secret123')
            ->call('saveStaff')
            ->assertHasErrors('newRole');

        $this->assertDatabaseMissing('users', ['email' => 'sneaky.lab@hospital.test']);
    }

    public function test_the_dean_may_not_mint_an_admin(): void
    {
        $tenant = $this->hospital();
        $this->actingAs($this->staff($tenant, 'moderator'));

        Livewire::test(StaffDirectory::class)
            ->call('createStaff')
            ->set('name', 'Dean Minted Admin')
            ->set('email', 'dean.admin@hospital.test')
            ->set('newRole', 'admin')
            ->set('password', 'secret123')
            ->set('password_confirmation', 'secret123')
            ->call('saveStaff')
            ->assertHasErrors('newRole');

        $this->assertDatabaseMissing('users', ['email' => 'dean.admin@hospital.test']);
    }

    public function test_the_dean_may_not_reach_an_admin_account_by_id(): void
    {
        $tenant = $this->hospital();
        $owner = $this->staff($tenant, 'admin');
        $this->actingAs($this->staff($tenant, 'moderator'));

        // The id is not something the Dean can see in the UI, so the rule has to
        // hold at the component, not just in the hidden buttons.
        Livewire::test(StaffDirectory::class)
            ->call('editStaff', $owner->id)
            ->assertForbidden();

        Livewire::test(StaffDirectory::class)
            ->call('deleteStaff', $owner->id)
            ->assertForbidden();

// An admin account is not suspendable from this screen by anyone, so the Dean
// gets the same answer as everyone else rather than a 403.
Livewire::test(StaffDirectory::class)
            ->call('toggleActive', $owner->id);

$this->assertDatabaseHas('users', ['id' => $owner->id, 'is_active' => true]);
    }

    public function test_the_admin_is_untouched_by_the_dean_and_still_editable_by_its_owner(): void
    {
        $tenant = $this->hospital();
        $owner = $this->staff($tenant, 'admin');

        $this->actingAs($this->staff($tenant, 'moderator'));
        Livewire::test(StaffDirectory::class)->assertViewHas('users', function ($users) use ($owner): bool {
            $row = $users->firstWhere('id', $owner->id);

            $this->assertNotNull($row);
            $this->assertFalse(app(StaffDirectory::class)->canManage($row));

            return true;
        });

        $this->actingAs($owner);
        Livewire::test(StaffDirectory::class)
            ->call('editStaff', $owner->id)
            ->assertSet('showForm', true);
    }

    public function test_a_doctor_may_not_reach_the_directory_at_all(): void
    {
        $tenant = $this->hospital();
        $this->actingAs($this->staff($tenant, 'doctor'));

        Livewire::test(StaffDirectory::class)->assertForbidden();
    }
}