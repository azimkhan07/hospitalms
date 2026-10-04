<?php

namespace App\Http\Livewire\SuperAdmin;

use App\Models\ClinicType;
use App\Models\Tenant;
use App\Services\TenantService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class TenantForm extends Component
{
    use WithFileUploads;

    public bool $show = false;

    public int $step = 1;

    public ?int $tenantId = null;

    public string $name = '';

    public string $slug = '';

    public string $mode = 'hospital';

    public string $status = 'active';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public string $city = '';

    public string $state = '';

    public string $country = '';

    public string $working_hours = '';

    public string $domain = '';

    public string $subdomain = '';

    public bool $create_admin = false;

    public string $admin_name = '';

    public string $admin_email = '';

    public string $admin_password = '';

    public $logo = null;

    public $hero_image = null;

    public ?string $existingLogo = null;

    public ?string $existingHero = null;

    public $beds = null;

    public $staff = null;

    public $floors = null;

    public string $departments = '';

    public string $services = '';

    public bool $has_lab = false;

    public bool $has_ot = false;

    public bool $has_ambulance = false;

    /**
     * Hospital onboarding (PLAN.md §9b): private rooms are optional and asked
     * about at creation. "Yes/No" controls the conditional quantity field.
     */
    public bool $private_room_enabled = false;
    public $private_room_count = null;

    /**
     * What kind of facility this is (PLAN.md §9c.1). A clinic is not one thing:
     * a dental clinic has no ward and a skin clinic has no labouratorist.
     */
    public string $clinic_type_id = '';

    /**
     * The roles the facility actually bought, ticked by the platform (PLAN.md
     * §9c.2). Defaults to whatever the chosen type suggests; the tick boxes are
     * the final word because only the platform knows what was sold.
     *
     * @var array<int, string>
     */
    public array $requiredRoles = [];

    /**
     * True while the tick list is untouched, so a hand-picked list is never
     * silently overwritten by the next type change.
     *
     * Must be public: Livewire only carries public properties between requests,
     * so a protected flag would silently reset on every round trip and undo the
     * hand-picked list each time the type changed. Tampering with it is
     * harmless -- validation and the persisted list never read it.
     */
    public bool $rolesPickedManually = false;

    /** Roles this mode could ever have, regardless of type. */
    public function modeRoleSlugs(): array
    {
        $roles = hms_enabled_roles($this->mode);

        if (is_array($roles)) {
            return $roles;
        }

        return config('hms.all_roles', \App\Models\Role::pluck('slug')->all());
    }

    /**
     * The roles a tick box may hold right now: a real role the platform can sell,
     * minus super_admin (never assignable from inside a facility), minus
     * anything this mode forbids.
     *
     * @return array<int, string>
     */
    public function assignableRoleSlugs(): array
    {
        $real = \App\Models\Role::where('slug', '!=', 'super_admin')->pluck('slug')->all();

        return array_values(array_intersect($real, $this->modeRoleSlugs()));
    }

    /** Types offered for the current mode. */
    public function availableTypes()
    {
        return ClinicType::active()->forMode($this->mode)->orderBy('sort_order')->get();
    }

    /**
     * Picking a type re-suggests the role list, but only while the platform has
     * not hand-picked one -- otherwise changing the type after the fact would
     * wipe a deliberate staffing plan.
     */
    public function updatedMode(): void
    {
        $this->clinic_type_id = '';
        $this->applySuggestedRoles();
    }

    public function updatedClinicTypeId(): void
    {
        // Deliberately does not clear rolesPickedManually: a staffing plan the
        // platform hand-picked must survive a type change, and only the explicit
        // "Reset to suggested" button may overwrite it.
        $this->applySuggestedRoles();
    }

    /**
     * A tick box was touched. The list is now deliberate, so stop re-suggesting
     * it on every type change -- only an explicit "Reset to suggested" may
     * overwrite what the platform picked.
     *
     * Driven by Livewire's own updated hook, so a forged payload cannot slip a
     * hand-picked list past this.
     */
    public function updatedRequiredRoles(): void
    {
        $this->rolesPickedManually = true;
    }

    public function useSuggestedRoles(): void
    {
        $this->rolesPickedManually = false;
        $this->applySuggestedRoles();
    }

    protected function applySuggestedRoles(): void
    {
        if ($this->rolesPickedManually) {
            return;
        }

        $type = ClinicType::find($this->clinic_type_id ?: null);

        $suggested = $type ? $type->suggestedRoleSlugs() : $this->defaultRolesForMode();

        // a tenant can never be given a role its mode forbids
        $this->requiredRoles = array_values(array_intersect($suggested, $this->modeRoleSlugs()));
    }

    /**
     * @return array<int, string>
     */
    protected function defaultRolesForMode(): array
    {
        return $this->mode === 'clinic'
            ? ['admin', 'receptionist', 'doctor', 'pharmacist']
            : ['admin', 'moderator', 'receptionist', 'doctor', 'nurse', 'pharmacist',
                'laboratorist', 'storekeeper', 'accountant', 'hr'];
    }

    /**
     * Where the hospital is. Staff may only sign in from inside this radius;
     * the tenant admin is exempt and may sign in from anywhere.
     */
    public $latitude = null;

    public $longitude = null;

    public $geo_radius_meters = 200;

    /**
     * Fill the coordinates from the browser so the platform does not have to
     * read them off a map.
     */
    public function useCurrentLocation(): void
    {
        $this->dispatch('capture-location');
    }

    #[On('open-tenant-form')]
    public function open($id = null): void
    {
        $this->resetForm();

        if ($id) {
            $this->loadTenant((int) $id);
        }

        $this->show = true;
    }

    public function close(): void
    {
        $this->show = false;
        $this->resetForm();
    }

    protected function loadTenant(int $id): void
    {
        $tenant = Tenant::findOrFail($id);
        $facilities = $tenant->facilities ?? [];

        $this->tenantId = $tenant->id;
        $this->name = (string) $tenant->name;
        $this->slug = (string) $tenant->slug;
        $this->mode = (string) $tenant->mode;
        $this->status = (string) $tenant->status;
        $this->phone = (string) $tenant->phone;
        $this->email = (string) $tenant->email;
        $this->address = (string) $tenant->address;
        $this->city = (string) $tenant->city;
        $this->state = (string) ($tenant->state ?? '');
        $this->country = (string) $tenant->country;
        $this->working_hours = (string) $tenant->working_hours;
        $this->domain = (string) $tenant->domain;
        $this->subdomain = (string) $tenant->subdomain;
        $this->existingLogo = $tenant->logo;
        $this->existingHero = $tenant->hero_image;
        $this->latitude = $tenant->latitude;
        $this->longitude = $tenant->longitude;
        $this->geo_radius_meters = $tenant->geo_radius_meters ?: 200;

        $this->private_room_enabled = (bool) $tenant->private_room_enabled;
        $this->private_room_count = $tenant->private_room_count;

        $this->clinic_type_id = (string) ($tenant->clinic_type_id ?: '');
        $this->requiredRoles = $tenant->requiredRoles()->pluck('slug')->all();
        $this->rolesPickedManually = true;

        $this->beds = $facilities['beds'] ?? null;
        $this->staff = $facilities['staff'] ?? null;
        $this->floors = $facilities['floors'] ?? null;
        $this->departments = implode(', ', $facilities['departments'] ?? []);
        $this->services = implode(', ', $facilities['services'] ?? []);
        $this->has_lab = (bool) ($facilities['has_lab'] ?? false);
        $this->has_ot = (bool) ($facilities['has_ot'] ?? false);
        $this->has_ambulance = (bool) ($facilities['has_ambulance'] ?? false);

        $this->create_admin = false;
    }

    public function resetForm(): void
    {
        $this->reset([
        'step', 'tenantId', 'name', 'slug', 'phone', 'email', 'address',
        'city', 'state', 'country', 'working_hours', 'domain', 'subdomain',
            'admin_name', 'admin_email', 'admin_password', 'logo', 'hero_image',
            'existingLogo', 'existingHero', 'beds', 'staff', 'floors',
            'departments', 'services', 'has_lab', 'has_ot', 'has_ambulance',
            'latitude', 'longitude', 'geo_radius_meters',
            'private_room_enabled', 'private_room_count', 'requiredRoles',
        ]);

        $this->mode = 'hospital';
        $this->status = 'active';
        $this->create_admin = true;
        $this->step = 1;
        $this->geo_radius_meters = 200;
        $this->private_room_enabled = false;
        $this->private_room_count = null;
        $this->clinic_type_id = '';
        $this->rolesPickedManually = false;
        $this->requiredRoles = $this->defaultRolesForMode();
    }

    public function updatedName(): void
    {
        if ($this->tenantId === null) {
            $this->slug = Str::slug($this->name);
        }
    }

    public function next(): void
    {
        $rules = $this->rules();
        unset($rules['admin_name'], $rules['admin_email'], $rules['admin_password']);

        $this->validate($rules);

        $this->step = 2;
    }

    public function back(): void
    {
        $this->step = 1;
    }

    protected function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:150', Rule::unique('tenants', 'slug')->ignore($this->tenantId)],
            'mode' => ['required', Rule::in(array_keys(config('hms.modes', [])))],
            'status' => ['required', Rule::in(['active', 'suspended', 'trial'])],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:200'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'working_hours' => ['nullable', 'string', 'max:150'],
            'domain' => ['nullable', 'string', 'max:150', Rule::unique('tenants', 'domain')->ignore($this->tenantId)],
            'subdomain' => ['nullable', 'string', 'max:150', Rule::unique('tenants', 'subdomain')->ignore($this->tenantId)],
            'beds' => ['nullable', 'integer', 'min:0'],
            'staff' => ['nullable', 'integer', 'min:0'],
            'floors' => ['nullable', 'integer', 'min:0'],
            'departments' => ['nullable', 'string'],
            'services' => ['nullable', 'string'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'hero_image' => ['nullable', 'image', 'max:4096'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'geo_radius_meters' => ['nullable', 'integer', 'between:20,5000'],
        ];

        if ($this->mode === 'hospital' && $this->private_room_enabled) {
            $rules['private_room_count'] = ['required', 'integer', 'min:1', 'max:999'];
        } else {
            $rules['private_room_count'] = ['nullable', 'integer', 'min:0'];
        }

        // A type is what a facility is, and at least one role has to be ticked or the
        // tenant would come up empty and be un-usable.
        $rules['clinic_type_id'] = ['required', Rule::exists('clinic_types', 'id')->where('is_active', true)];

        $rules['requiredRoles'] = ['required', 'array', 'min:1'];
        // Allowed = every real role, minus the platform one, minus anything the
        // mode forbids. Rejecting out-of-mode roles here (rather than dropping
        // them silently on save) tells the platform straight away that a nurse
        // cannot be ticked on a clinic.
        $rules['requiredRoles.*'] = [
            'string',
            Rule::in($this->assignableRoleSlugs()),
        ];

        if ($this->create_admin) {
            $rules['admin_name'] = ['required', 'string', 'max:150'];
            $rules['admin_email'] = ['required', 'email', 'max:150', Rule::unique('users', 'email')];
            $rules['admin_password'] = ['required', 'string', 'min:6'];
        }

        return $rules;
    }

    public function save(TenantService $service): void
    {
        $this->validate($this->rules());

        $data = [
            'name' => $this->name,
            'slug' => $this->slug,
            'mode' => $this->mode,
            'status' => $this->status,
            'phone' => $this->phone ?: null,
            'email' => $this->email ?: null,
            'address' => $this->address ?: null,
            'city' => $this->city ?: null,
            'country' => $this->country ?: null,
            'working_hours' => $this->working_hours ?: null,
            'domain' => $this->domain ?: null,
            'subdomain' => $this->subdomain ?: null,
            'facilities' => $this->facilitiesPayload(),
            'latitude' => $this->latitude !== '' && $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== '' && $this->longitude !== null ? (float) $this->longitude : null,
            'geo_radius_meters' => $this->geo_radius_meters ?: 200,
            'clinic_type_id' => (int) $this->clinic_type_id,
            // private rooms are a hospital concept; a clinic never carries the
            // flag, so switching a facility to clinic cannot leave it "on".
            'private_room_enabled' => $this->mode === 'hospital' && (bool) $this->private_room_enabled,
            'private_room_count' => $this->mode === 'hospital' && $this->private_room_enabled
                ? (int) ($this->private_room_count ?? 0)
                : null,
        ];

        if ($this->logo) {
            $data['logo'] = $this->logo->store('tenants', 'public');
        }

        if ($this->hero_image) {
            $data['hero_image'] = $this->hero_image->store('tenants', 'public');
        }

        if ($this->tenantId) {
            $tenant = Tenant::findOrFail($this->tenantId);
            $service->update($tenant, $data);
            $message = 'Tenant updated successfully.';
        } else {
            $admin = $this->create_admin ? [
                'name' => $this->admin_name,
                'email' => $this->admin_email,
                'password' => $this->admin_password,
            ] : null;

            $tenant = $service->create($data, $admin);
            $message = 'Tenant created successfully.';
        }

        $this->seedDefaultSettings($tenant);

        // The ticked role list is the facility's staffing plan and is the only
        // thing that drives its sidebar, its staff form and its Dean's
        // permissions (PLAN.md §9c.2). Intersect with the mode so a forged
        // payload cannot widen a facility past what its mode allows.
        $tenant->syncRequiredRoles(array_intersect($this->requiredRoles, $this->modeRoleSlugs()));

        $this->show = false;
        $this->resetForm();

        $this->dispatch('tenant-saved');
        session()->flash('sanotice', $message);
    }

    protected function facilitiesPayload(): array
    {
        return [
            'beds' => $this->beds !== null ? (int) $this->beds : null,
            'staff' => $this->staff !== null ? (int) $this->staff : null,
            'floors' => $this->floors !== null ? (int) $this->floors : null,
            'departments' => $this->csv($this->departments),
            'services' => $this->csv($this->services),
            'has_lab' => $this->has_lab,
            'has_ot' => $this->has_ot,
            'has_ambulance' => $this->has_ambulance,
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function csv(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn ($v) => trim($v))
            ->filter()
            ->values()
            ->all();
    }

    protected function seedDefaultSettings(Tenant $tenant): void
    {
        // Placeholder for per-tenant settings provisioning (Phase 11).
    }

    public function render()
    {
        $roleLabels = [];

        foreach (\App\Models\Role::where('slug', '!=', 'super_admin')->get() as $role) {
            $roleLabels[$role->slug] = hms_role_label_for_slug($role->slug);
        }

        return view('livewire.super-admin.tenant-form', [
            'modes' => config('hms.modes', []),
            'clinicTypes' => $this->availableTypes(),
            'modeRoleSlugs' => $this->modeRoleSlugs(),
            'roleLabels' => $roleLabels,
        ]);
    }
}
