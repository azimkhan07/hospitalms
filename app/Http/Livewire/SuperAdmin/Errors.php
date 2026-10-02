<?php

namespace App\Http\Livewire\SuperAdmin;

use App\Models\SystemError;
use Livewire\Component;
use Livewire\WithPagination;

class Errors extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public string $search = '';

    public string $level = '';

    public string $status = 'unresolved';

    public ?int $expanded = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedLevel(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function toggle(int $id): void
    {
        $this->expanded = $this->expanded === $id ? null : $id;
    }

    public function resolve(int $id): void
    {
        SystemError::whereKey($id)->update(['resolved_at' => now()]);
        session()->flash('sanotice', 'Error marked as resolved.');
    }

    public function unresolve(int $id): void
    {
        SystemError::whereKey($id)->update(['resolved_at' => null]);
        session()->flash('sanotice', 'Error reopened.');
    }

    public function delete(int $id): void
    {
        SystemError::whereKey($id)->delete();
        session()->flash('sanotice', 'Error deleted.');
    }

    public function clearResolved(): void
    {
        $deleted = SystemError::whereNotNull('resolved_at')->delete();
        session()->flash('sanotice', "{$deleted} resolved error(s) cleared.");
    }

    public function render()
    {
        $logs = SystemError::with('tenant')
            ->when($this->status === 'unresolved', fn ($q) => $q->whereNull('resolved_at'))
            ->when($this->status === 'resolved', fn ($q) => $q->whereNotNull('resolved_at'))
            ->when($this->level !== '', fn ($q) => $q->where('level', $this->level))
            ->when($this->search !== '', function ($q) {
                $term = '%'.mb_strtolower($this->search).'%';
                $q->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(message) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(exception_class) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(url) LIKE ?', [$term]);
                });
            })
            ->orderByDesc('id')
            ->paginate(20);

        return view('livewire.super-admin.errors', [
            'logs' => $logs,
            'counts' => [
                'unresolved' => SystemError::whereNull('resolved_at')->count(),
                'resolved' => SystemError::whereNotNull('resolved_at')->count(),
                'critical' => SystemError::where('level', 'critical')->whereNull('resolved_at')->count(),
            ],
        ]);
    }
}
