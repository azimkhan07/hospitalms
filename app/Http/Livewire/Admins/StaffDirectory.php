<?php

namespace App\Http\Livewire\Admins;

use App\Models\Role;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('admins.layouts.app')]
class StaffDirectory extends Component
{
    public string $role = '';

    public string $search = '';

    // --- create / edit form -------------------------------------------------
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $designation = '';

    public string $department = '';

    public string $newRole = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(?string $role = null): void
    {
        if ($role && hms_role_enabled($role)) {
            $this->role = $role;
        }
    }

    /**
     * Staff belonging to the signed-in tenant.
     *
     * Platform accounts have no tenant, so they never surface in a tenant
     * directory and can never be toggled from one.
     */
    private function tenantStaff(): \Illuminate\Database\Eloquent\Builder
    {
        return User::where('tenant_id', auth()->user()->tenant_id);
    }

    /**
     * Roles this facility may actually staff.
     *
     * A clinic cannot hire a nurse, so the create form must not offer one; and
     * "super_admin" is a platform account that must never be assignable from
     * inside a tenant (PLAN.md Phase 2).
     *
     * @return \Illuminate\Support\Collection<int, Role>
     */
    public function assignableRoles()
    {
        $enabled = hms_enabled_roles();

        return Role::where('slug', '!=', 'super_admin')
            ->when(is_array($enabled), fn ($q) => $q->whereIn('slug', $enabled))
            ->orderBy('level')
            ->get();
    }

    // --- create / edit ------------------------------------------------------

    public function createStaff(): void
    {
        $this->guardStaff();
        $this->resetFormFields();
        $this->editingId = null;
        $this->showForm = true;
    }

    public function editStaff(int $id): void
    {
        $this->guardStaff();
        $user = $this->tenantStaff()->findOrFail($id);

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = (string) $user->phone;
        $this->designation = (string) $user->designation;
        $this->department = (string) $user->department;
        $this->newRole = (string) $user->role?->slug;
        $this->password = '';
        $this->password_confirmation = '';
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->resetFormFields();
        $this->editingId = null;
        $this->showForm = false;
    }

    public function saveStaff(): void
    {
        $this->guardStaff();

        $roles = $this->assignableRoles()->pluck('slug')->all();

        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($this->editingId)],
            'phone' => ['nullable', 'string', 'max:30'],
            'designation' => ['nullable', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:100'],
            // The role has to be one the mode allows, checked against the live
            // list rather than trusted from the form.
            'newRole' => ['required', Rule::in($roles)],
        ];

        if ($this->editingId === null) {
            $rules['password'] = ['required', 'string', 'min:6', 'confirmed'];
        }

        $this->validate($rules);

        $roleId = Role::where('slug', $this->newRole)->value('id');

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone ?: null,
            'designation' => $this->designation ?: null,
            'department' => $this->department ?: null,
            'role_id' => $roleId,
        ];

        if ($this->editingId === null) {
            $data['password'] = bcrypt($this->password);
            $data['tenant_id'] = auth()->user()->tenant_id;
            $data['is_active'] = true;
            $data['created_by'] = auth()->id();

            $user = User::create($data);
            session()->flash('message', 'Staff account created for '.$user->email.'.');
        } else {
            $user = $this->tenantStaff()->findOrFail($this->editingId);

            if ($this->password !== '') {
                $this->validate(['password' => ['required', 'string', 'min:6', 'confirmed']]);
                $data['password'] = bcrypt($this->password);
            }

            $user->update($data);
            session()->flash('message', 'Staff account updated for '.$user->email.'.');
        }

        $this->cancelForm();
        $this->role = '';
    }

    public function deleteStaff(int $id): void
    {
        $this->guardStaff();
        $user = $this->tenantStaff()->findOrFail($id);

        if ($user->id === auth()->id()) {
            session()->flash('error', 'You cannot remove your own account.');

            return;
        }

        // The accessor treats the tenant admin as a super admin too, so name the
        // roles explicitly rather than leaving the message misleading.
        if (in_array($user->roleSlug(), ['super_admin', 'admin'], true)) {
            session()->flash('error', 'An admin or platform account cannot be removed from here.');

            return;
        }

        $user->delete();
        session()->flash('message', 'Staff account removed.');
    }

    protected function resetFormFields(): void
    {
        $this->name = '';
        $this->email = '';
        $this->phone = '';
        $this->designation = '';
        $this->department = '';
        $this->newRole = '';
        $this->password = '';
        $this->password_confirmation = '';
    }

    /**
     * Creating, editing and removing staff accounts is an admin job.
     */
    protected function guardStaff(): void
    {
        abort_unless(hms_can('staff.manage'), 403);
    }

    public function render()
    {
        if (! hms_can('staff')) {
            abort(403);
        }

        $enabled = hms_enabled_roles();

        $users = $this->tenantStaff()
            ->with('role:id,name,slug,level')
            ->when(is_array($enabled), fn ($q) => $q->whereHas(
                'role',
                fn ($r) => $r->whereIn('slug', $enabled)
            ))
            ->when($this->role !== '', fn ($q) => $q->whereHas(
                'role',
                fn ($r) => $r->where('slug', $this->role)
            ))
            ->when($this->search !== '', function ($q) {
                $term = '%'.mb_strtolower($this->search).'%';
                $q->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(designation) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(department) LIKE ?', [$term]);
                });
            })
            ->orderBy('name')
            ->get();

        return view('livewire.admins.staff-directory', [
            'users' => $users,
            'roles' => $this->assignableRoles()
                ->loadCount(['users' => fn ($q) => $q->where('tenant_id', auth()->user()->tenant_id)]),
            'canManage' => hms_can('staff.manage'),
        ]);
    }

    public function toggleActive(int $id): void
    {
        if (! hms_can('staff')) {
            abort(403);
        }

        $user = $this->tenantStaff()->findOrFail($id);

        if ($user->is_super_admin) {
            session()->flash('error', 'The super admin account cannot be disabled.');

            return;
        }

        $user->update(['is_active' => ! $user->is_active]);
    }
}