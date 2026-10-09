<?php

namespace App\Http\Livewire\Admins;

use App\Models\doctor;
use App\Models\employee;
use App\Models\Role;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('admins.layouts.app')]
class IdCards extends Component
{
    public string $role = '';

    public string $search = '';

    public function mount(?string $role = null): void
    {
        if (! hms_idcards_enabled()) {
            abort(404);
        }

        // The ?role= shortcut from the sidebar must not expose a role the
        // facility never ticked, so it is checked against the same list the
        // directory itself uses.
        if ($role && in_array($role, hms_tenant_required_roles(), true)) {
            $this->role = $role;
        }
    }

    /**
     * Staff belonging to the signed-in tenant.
     */
    private function tenantStaff(): \Illuminate\Database\Eloquent\Builder
    {
        return User::where('tenant_id', auth()->user()->tenant_id);
    }

    /**
     * Roles this facility may actually staff, same source as StaffDirectory.
     *
     * @return \Illuminate\Support\Collection<int, Role>
     */
    public function roleOptions()
    {
        $ticked = hms_tenant_required_roles();

        return Role::where('slug', '!=', 'super_admin')
            ->when($ticked !== [], fn ($q) => $q->whereIn('slug', $ticked))
            ->orderBy('level')
            ->get(['id', 'name', 'slug']);
    }

    /**
     * The employee row behind a login: a doctor's profile employee first, then
     * an employee matched by email. This is the repo's users<->doctor<->employee
     * linkage (see StaffDirectory::syncDoctorProfile).
     */
    private function employeeFor(User $user): ?employee
    {
        $profile = doctor::where('user_id', $user->id)->first();

        if ($profile && $profile->employ) {
            return $profile->employ;
        }

        return employee::where('email', $user->email)->first();
    }

    /**
     * Employee code when the employee row carries one, else STR-0000.
     */
    private function staffCode(User $user, ?employee $employee): string
    {
        $code = $employee?->getAttribute('code');

        return filled($code) ? (string) $code : 'STR-'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT);
    }

    public function render()
    {
        if (! hms_can('staff')) {
            abort(403);
        }

        $ticked = hms_tenant_required_roles();

        $users = $this->tenantStaff()
            ->with('role:id,name,slug,level')
            ->where('is_active', true)
            ->when($ticked !== [], fn ($q) => $q->whereHas(
                'role',
                fn ($r) => $r->whereIn('slug', $ticked)
            ))
            ->when($this->role !== '', fn ($q) => $q->whereHas(
                'role',
                fn ($r) => $r->where('slug', $this->role)
            ))
            ->when($this->search !== '', function ($q) {
                $term = '%'.mb_strtolower($this->search).'%';
                $q->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(phone) LIKE ?', [$term]);
                });
            })
            ->orderBy('name')
            ->get();

        $cards = $users->map(function (User $user) {
            $employee = $this->employeeFor($user);

            return [
                'id' => $user->id,
                'name' => $user->name,
                'role' => hms_role_label($user),
                'role_slug' => (string) $user->roleSlug(),
                'designation' => $user->designation,
                'department' => $user->department,
                'staff_code' => $this->staffCode($user, $employee),
                'phone' => $user->phone,
                'email' => $user->email,
                'photo' => storage_url($employee?->image, 'employee-placeholder.jpg'),
            ];
        })->all();

        return view('livewire.admins.id-cards', [
            'cards' => $cards,
            'roles' => $this->roleOptions(),
        ]);
    }
}
