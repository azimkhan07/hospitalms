<?php

namespace App\Http\Livewire\Admins;

use App\Models\ClinicType;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('admins.layouts.app')]
/**
 * Facility overview inside a tenant admin panel (PLAN.md section 9c.3): every
 * clinic and hospital the platform hosts, which ones still wait for an admin,
 * and which ticked roles are still unstaffed. Read-only — the platform panel
 * is where those queues are actually worked.
 */
class Facilities extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    #[Url]
    public string $search = '';

    #[Url]
    public string $mode = '';

    #[Url]
    public string $type = '';

    #[Url]
    public bool $unassignedOnly = false;

    public function updatedSearch(): void
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
        $this->mode = '';
        $this->type = '';
        $this->unassignedOnly = false;
        $this->resetPage();
    }

    public function render()
    {
        abort_unless(hms_can('facilities'), 403);

        $facilities = Tenant::with(['clinicType', 'requirements.role', 'admins'])
            ->withCount('users')
            ->when($this->search !== '', function ($q) {
                $term = '%'.mb_strtolower($this->search).'%';
                $q->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(slug) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$term]);
                });
            })
            ->when($this->mode !== '', fn ($q) => $q->where('mode', $this->mode))
            ->when($this->type !== '', fn ($q) => $q->where('clinic_type_id', $this->type))
            ->when($this->unassignedOnly, function ($q) {
                // "nobody has been made admin of it yet"
                $q->whereDoesntHave('users', function ($q) {
                    $q->whereHas('role', fn ($r) => $r->where('slug', 'admin'));
                });
            })
            ->orderBy('name')
            ->paginate(10);

        // Staff per role per facility, and beds per facility, in two grouped
        // queries rather than N+1 lookups.
        $roleCounts = $this->headcounts();
        $bedCounts = DB::table('beds')
            ->whereNull('deleted_at')
            ->groupBy('tenant_id')
            ->selectRaw('tenant_id, COUNT(*) as total')
            ->pluck('total', 'tenant_id');

        foreach ($facilities as $facility) {
            $facility->setAttribute('role_counts', $roleCounts[$facility->id] ?? []);
            $facility->setAttribute('has_admin', $facility->admins->isNotEmpty());
            $facility->setAttribute('bed_count', (int) ($bedCounts[$facility->id] ?? 0));
        }

        return view('livewire.admins.facilities', [
            'facilities' => $facilities,
            'modes' => config('hms.modes', []),
            'clinicTypes' => ClinicType::active()->orderBy('sort_order')->get(),
        ]);
    }

    /**
     * @return array<int, array<string, int>>
     */
    protected function headcounts(): array
    {
        $rows = DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->whereNotNull('users.tenant_id')
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
}