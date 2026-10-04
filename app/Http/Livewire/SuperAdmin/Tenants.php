<?php

namespace App\Http\Livewire\SuperAdmin;

use App\Models\ClinicType;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class Tenants extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public string $search = '';

    public string $status = '';

    public string $mode = '';

    public string $type = '';

    /**
     * Show only facilities nobody has been made admin of yet.
     *
     * A freshly created tenant with no admin is un-sellable and un-usable, so
     * this is the queue the office actually works through (PLAN.md section 9c.3).
     */
    public bool $unassignedOnly = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedMode(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function updatedUnassignedOnly(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->status = '';
        $this->mode = '';
        $this->type = '';
        $this->unassignedOnly = false;
        $this->resetPage();
    }

    #[On('tenant-saved')]
    public function refreshList(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->dispatch('open-tenant-form');
    }

    public function edit(int $id): void
    {
        $this->dispatch('open-tenant-form', id: $id);
    }

    /**
     * Send the platform straight to assigning an admin for this facility.
     *
     * A redirect rather than a second inline form: the admin list is its own
     * screen, and the queue is worked through from here.
     */
    public function assignAdmin(int $id): void
    {
        $this->redirect(route('superadmin.admins', ['tenant' => $id]), navigate: false);
    }

    public function toggleStatus(int $id): void
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->status = $tenant->status === 'active' ? 'suspended' : 'active';
        $tenant->save();

        session()->flash('sanotice', "{$tenant->name} is now {$tenant->status}.");
    }

    public function delete(int $id): void
    {
        $tenant = Tenant::findOrFail($id);
        $name = $tenant->name;
        $tenant->delete();

        session()->flash('sanotice', "{$name} was archived.");
    }

    public function render()
    {
        $tenants = Tenant::with(['clinicType', 'requirements.role'])
            ->withCount('users')
            ->when($this->search !== '', function ($q) {
                $term = '%'.mb_strtolower($this->search).'%';
                $q->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(slug) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(domain) LIKE ?', [$term]);
                });
            })
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->mode !== '', fn ($q) => $q->where('mode', $this->mode))
            ->when($this->type !== '', fn ($q) => $q->where('clinic_type_id', $this->type))
            ->when($this->unassignedOnly, function ($q) {
                // "nobody has been made admin of it yet"
                $q->whereDoesntHave('users', function ($q) {
                    $q->whereHas('role', fn ($r) => $r->where('slug', 'admin'));
                });
            })
            ->orderByDesc('id')
            ->paginate(10);

        // Headcount per required role, per tenant, in one query rather than N.
        $roleCounts = $this->headcounts();

        foreach ($tenants as $tenant) {
            $tenant->setAttribute('role_counts', $roleCounts[$tenant->id] ?? []);
            $tenant->setAttribute('admin_count', $tenant->role_counts['admin'] ?? 0);
            $tenant->setAttribute('bed_count', DB::table('beds')
                ->where('tenant_id', $tenant->id)
                ->whereNull('deleted_at')
                ->count());
        }

        return view('livewire.super-admin.tenants', [
            'tenants' => $tenants,
            'modes' => config('hms.modes', []),
            'clinicTypes' => ClinicType::active()->orderBy('sort_order')->get(),
            'stats' => $this->globalStats(),
        ]);
    }

    /**
     * Staff per role per tenant, for the "roles it still needs" column.
     *
     * @return array<int, array<string, int>>
     */
    protected function headcounts(): array
    {
        $rows = DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->where('users.tenant_id', '!=', null)
            ->whereNull('users.deleted_at')
            ->groupBy('users.tenant_id', 'roles.slug')
            ->selectRaw('users.tenant_id, roles.slug, COUNT(*) as total')
            ->get();

        $out = [];

        foreach ($rows as $row) {
            $out[$row->tenant_id][$row->slug] = (int) $row->total;
        }

        return $out;
    }

    /**
     * The sales-level view (PLAN.md section 9c.5): how many facilities exist,
     * what kinds, and how many are still unassigned.
     *
     * @return array<string, mixed>
     */
    protected function globalStats(): array
    {
        $base = Tenant::query();

        $byType = (clone $base)
            ->join('clinic_types', 'clinic_types.id', '=', 'tenants.clinic_type_id')
            ->groupBy('clinic_types.name')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->selectRaw('clinic_types.name, COUNT(*) as total')
            ->pluck('total', 'name');

        return [
            'total' => (clone $base)->count(),
            'hospitals' => (clone $base)->where('mode', 'hospital')->count(),
            'clinics' => (clone $base)->where('mode', 'clinic')->count(),
            'unassigned' => (clone $base)->whereDoesntHave('users', function ($q) {
                $q->whereHas('role', fn ($r) => $r->where('slug', 'admin'));
            })->count(),
            'byType' => $byType,
            'staff' => DB::table('users')->whereNotNull('tenant_id')->whereNull('deleted_at')->count(),
        ];
    }
}