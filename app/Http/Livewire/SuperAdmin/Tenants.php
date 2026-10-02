<?php

namespace App\Http\Livewire\SuperAdmin;

use App\Models\Tenant;
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
        $tenants = Tenant::withCount('users')
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
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.super-admin.tenants', [
            'tenants' => $tenants,
            'modes' => config('hms.modes', []),
        ]);
    }
}
