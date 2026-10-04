<?php

namespace App\Http\Livewire\SuperAdmin;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class TenantAdmins extends Component
{
    use WithPagination;

    public bool $showForm = false;
    public ?int $adminId = null;

    public string $search = '';
    public string $status = '';
    public string $tenantId = '';

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public int $is_active = 1;
    public ?int $tenant_id = null;

    protected function rules(): array
    {
        $unique = Rule::unique('users', 'email');
        if ($this->adminId) {
            $unique = $unique->ignore($this->adminId);
        }

        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', $unique],
            'is_active' => ['required', 'boolean'],
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
        ];

        if (! $this->adminId) {
            $rules['password'] = ['required', 'string', 'min:6'];
        } else {
            $rules['password'] = ['nullable', 'string', 'min:6'];
        }

        return $rules;
    }

    public function mount(): void
    {
        // The tenants screen sends the platform here with ?tenant=<id> when it
        // asks for an admin to be assigned, so the form opens on that facility
        // rather than making them hunt for it in the dropdown.
        $tenantId = request()->integer('tenant') ?: null;

        if ($tenantId && Tenant::where('id', $tenantId)->exists()) {
            $this->tenant_id = $tenantId;
            $this->showForm = true;
        }
    }

    public function openCreate(): void
    {
        // Arriving from the facilities list with ?tenant=<id> preselects that
        // facility. Opening a blank form must keep that choice rather than
        // silently wiping it back to "-- select --".
        $preselectedTenant = $this->tenant_id;

        $this->resetForm();

        $this->tenant_id = $preselectedTenant;
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $this->resetForm();
        $this->adminId = $id;
        $admin = User::findOrFail($id);
        $this->name = $admin->name;
        $this->email = $admin->email;
        $this->is_active = (int) $admin->is_active;
        $this->tenant_id = $admin->tenant_id;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
    }

    public function resetForm(): void
    {
        $this->adminId = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->is_active = 1;
        $this->tenant_id = null;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->validate();

        $roleId = Role::where('slug', 'admin')->value('id');
        if (! $roleId) {
            session()->flash('sanotice', 'Admin role not found. Run seeders.');
            return;
        }

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'role_id' => $roleId,
            'designation' => 'Administrator',
            'department' => 'Administration',
            'is_active' => (bool) $this->is_active,
            'tenant_id' => $this->tenant_id,
        ];

        if ($this->password) {
            $data['password'] = bcrypt($this->password);
        }

        if ($this->adminId) {
            User::where('id', $this->adminId)->update($data);
            session()->flash('sanotice', 'Admin updated successfully.');
        } else {
            User::create($data);
            session()->flash('sanotice', 'Admin created successfully.');
        }

        $this->showForm = false;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $admin = User::find($id);
        if ($admin) {
            $admin->delete();
            session()->flash('sanotice', 'Admin deleted.');
        }
    }

    public function render()
    {
        $admins = User::with(['role', 'tenant'])
            ->whereHas('role', fn ($q) => $q->where('slug', 'admin'))
            ->when($this->search, function ($q) {
                $q->where(function ($qq) {
                    $qq->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%')
                        ->orWhereHas('tenant', fn ($t) => $t->where('name', 'like', '%'.$this->search.'%'));
                });
            })
            ->when($this->tenantId, fn ($q) => $q->where('tenant_id', $this->tenantId))
            ->when($this->status, function ($q) {
                $q->when($this->status === 'active', fn ($qq) => $qq->where('is_active', true))
                    ->when($this->status === 'inactive', fn ($qq) => $qq->where('is_active', false));
            })
            ->orderBy('name')
            ->paginate(15);

        $tenants = Tenant::orderBy('name')->get();
        $tenantsList = $tenants->sortBy('name');

        return view('livewire.super-admin.tenant-admins', [
            'admins' => $admins,
            'tenants' => $tenants,
            'tenantsList' => $tenantsList,
        ]);
    }
}
