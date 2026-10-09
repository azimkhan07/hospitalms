<?php

namespace Tests\Feature;

use App\Models\doctor;
use App\Models\employee;
use App\Models\Role;
use App\Models\Settings;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Staff ID cards are hospital-only: the panel, the print route and the API all
 * hide themselves (404) in clinic mode and still honour the staff module gate.
 */
class IdCardsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $admin;

    protected User $doctor;

    protected User $nurse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'ID Cards Hospital', 'slug' => 'id-cards-hospital',
            'mode' => 'hospital', 'status' => 'active', 'geo_radius_meters' => 200,
        ]);
        $this->tenant->syncRequiredRoles(['admin', 'doctor', 'nurse', 'receptionist']);

        $this->admin = $this->staff('admin', 'Ada Admin');
        $this->doctor = $this->staff('doctor', 'Dan Doctor');
        $this->nurse = $this->staff('nurse', 'Nina Nurse');

        $this->doctorProfile($this->doctor, 'employees/dr-dan.jpg');
    }

    protected function staff(string $slug, string $name): User
    {
        return User::factory()->create([
            'name' => $name,
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('slug', $slug)->value('id'),
            'is_active' => true,
        ]);
    }

    protected function doctorProfile(User $user, ?string $image = null): doctor
    {
        // tenant_id is not mass assignable on these models, so it is set
        // explicitly rather than relying on an authenticated tenant at setup.
        $employee = new employee([
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '0321-0000000',
            'position' => 'doctor',
            'status' => 'active',
            'image' => $image,
        ]);
        $employee->tenant_id = $this->tenant->id;
        $employee->save();

        $profile = new doctor([
            'employee_id' => $employee->id,
            'user_id' => $user->id,
        ]);
        $profile->tenant_id = $this->tenant->id;
        $profile->save();

        return $profile;
    }

    protected function clinicMode(): void
    {
        $this->tenant->update(['mode' => 'clinic']);
        Settings::updateOrCreate(['key' => 'institution_mode'], ['value' => 'clinic']);
    }

    public function test_hospital_mode_shows_the_id_card_panel(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('admin_id_cards'))
            ->assertOk()
            ->assertSee('ID Cards')
            ->assertSee('Dan Doctor')
            ->assertSee('Nina Nurse');
    }

    public function test_clinic_mode_hides_the_id_card_panel(): void
    {
        $this->clinicMode();

        $this->actingAs($this->admin);

        $this->get(route('admin_id_cards'))->assertNotFound();
    }

    public function test_a_single_card_prints_for_one_staff_member(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('admin_id_cards_print', ['user' => $this->doctor->id]))
            ->assertOk()
            ->assertSee('ID Cards Hospital')
            ->assertSee('Dan Doctor');
    }

    public function test_all_cards_print_for_the_active_roster(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('admin_id_cards_print'))
            ->assertOk()
            ->assertSee('Dan Doctor')
            ->assertSee('Nina Nurse');
    }

    public function test_the_api_lists_id_card_data(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson(route('api.v1.admin.id-cards'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [[
                    'id', 'name', 'role', 'designation', 'department',
                    'staff_code', 'phone', 'email', 'photo_url', 'hospital_name',
                ]],
            ]);

        $doctorCard = collect($response->json('data'))->firstWhere('name', 'Dan Doctor');

        $this->assertNotNull($doctorCard);
        $this->assertStringContainsString('employees/dr-dan.jpg', $doctorCard['photo_url']);
        $this->assertSame('ID Cards Hospital', $doctorCard['hospital_name']);
        $this->assertSame('STR-'.str_pad((string) $this->doctor->id, 4, '0', STR_PAD_LEFT), $doctorCard['staff_code']);
    }

    public function test_the_api_requires_the_staff_module(): void
    {
        $this->actingAs($this->doctor, 'sanctum');

        $this->getJson(route('api.v1.admin.id-cards'))->assertForbidden();
    }

    public function test_clinic_mode_hides_the_api(): void
    {
        $this->clinicMode();

        $this->actingAs($this->admin, 'sanctum');

        $this->getJson(route('api.v1.admin.id-cards'))->assertNotFound();
    }
}
