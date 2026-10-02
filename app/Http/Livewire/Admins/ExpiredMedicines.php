<?php

namespace App\Http\Livewire\Admins;

use App\Models\medicine;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('admins.layouts.app')]
class ExpiredMedicines extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public string $filter = 'expired';

    public string $search = '';

    public function render()
    {
        if (! hms_can('expiry')) {
            abort(403);
        }

        $query = medicine::query()->whereNull('deleted_at');

        if ($this->filter === 'expired') {
            $query->whereNotNull('expiry_date')->whereDate('expiry_date', '<=', today());
        } elseif ($this->filter === 'soon') {
            $query->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '>', today())
                ->whereDate('expiry_date', '<=', today()->addDays(30));
        } elseif ($this->filter === 'outofstock') {
            $query->where(function ($q) {
                $q->whereNull('stock')->orWhere('stock', '<=', 0);
            });
        }

        $medicines = $query
            ->when($this->search !== '', function ($q) {
                $term = '%'.mb_strtolower($this->search).'%';
                $q->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(code) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(manufacturer) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(batch_no) LIKE ?', [$term]);
                });
            })
            ->orderByRaw('expiry_date IS NULL')
            ->orderBy('expiry_date')
            ->paginate(15);

        return view('livewire.admins.expired-medicines', [
            'medicines' => $medicines,
            'counts' => [
                'expired' => medicine::whereNull('deleted_at')->whereNotNull('expiry_date')->whereDate('expiry_date', '<=', today())->count(),
                'soon' => medicine::whereNull('deleted_at')->whereNotNull('expiry_date')->whereDate('expiry_date', '>', today())->whereDate('expiry_date', '<=', today()->addDays(30))->count(),
                'outofstock' => medicine::whereNull('deleted_at')->where(function ($q) {
                    $q->whereNull('stock')->orWhere('stock', '<=', 0);
                })->count(),
            ],
        ]);
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
}