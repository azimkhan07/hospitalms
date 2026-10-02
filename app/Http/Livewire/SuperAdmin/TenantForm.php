<?php

namespace App\Http\Livewire\SuperAdmin;

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
        ]);

        $this->mode = 'hospital';
        $this->status = 'active';
        $this->create_admin = true;
        $this->step = 1;
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
        return view('livewire.super-admin.tenant-form', [
            'modes' => config('hms.modes', []),
        ]);
    }
}
