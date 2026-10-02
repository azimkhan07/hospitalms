<?php

namespace App\Http\Livewire\Admins;

use App\Models\stay;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('admins.layouts.app')]
class DischargeHistory extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public string $search = '';

    public string $typeFilter = '';

    public function render()
    {
        if (! hms_can('discharges')) {
            abort(403);
        }

        $stays = stay::with(['patient:id,name,bloodgroup', 'room.department:id,name'])
            ->whereNotNull('discharged_at')
            ->when($this->typeFilter !== '', fn ($q) => $q->where('discharge_type', $this->typeFilter))
            ->when($this->search !== '', function ($q) {
                $term = '%'.mb_strtolower($this->search).'%';
                $q->whereHas('patient', fn ($p) => $p->whereRaw('LOWER(name) LIKE ?', [$term]));
            })
            ->orderByDesc('discharged_at')
            ->paginate(15);

        return view('livewire.admins.discharge-history', [
            'stays' => $stays,
            'counts' => [
                'normal' => stay::where('discharge_type', 'normal')->count(),
                'referred' => stay::where('discharge_type', 'referred')->count(),
                'absconded' => stay::where('discharge_type', 'absconded')->count(),
            ],
            'totals' => [
                'count' => stay::whereNotNull('discharged_at')->count(),
                'amount' => stay::whereNotNull('discharged_at')->sum('total'),
            ],
        ]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }
}