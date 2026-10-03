<?php

namespace App\Http\Livewire\Admins;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('admins.layouts.app')]
class StaffDirectory extends Component
{
    public string $role = '';

    public string $search = '';

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
    private function tenantStaff(): Builder
    {
        return User::where('tenant_id', auth()->user()->tenant_id);
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
            'roles' => Role::withCount(['users' => fn ($q) => $q->where('tenant_id', auth()->user()->tenant_id)])
                ->when(is_array($enabled), fn ($q) => $q->whereIn('slug', $enabled))
                ->orderBy('level')
                ->get(),
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