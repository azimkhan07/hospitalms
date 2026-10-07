<?php

namespace App\Http\Livewire\Admins;

use App\Models\Scheme;
use App\Services\Accounting;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('admins.layouts.app')]
class Schemes extends Component
{
    use WithPagination;

    public string $search = '';
    public string $active = '';

    public bool $showForm = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $code = '';
    public string $provider = '';
    public string $coverageType = 'amount';
    public string $coverageValue = '';
    public string $description = '';
    public bool $isActive = true;

    public bool $showIncome = false;
    public ?int $incomeSchemeId = null;
    public string $incomeAmount = '';
    public string $incomeNote = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->active = '';
        $this->resetPage();
    }

    public function createScheme(): void
    {
        $this->guardManage();
        $this->resetFormFields();
        $this->editingId = null;
        $this->showForm = true;
    }

    public function editScheme(int $id): void
    {
        $this->guardManage();
        $scheme = Scheme::findOrFail($id);
        $this->editingId = $scheme->id;
        $this->name = (string) $scheme->name;
        $this->code = (string) $scheme->code;
        $this->provider = (string) $scheme->provider;
        $this->coverageType = $scheme->coverage_type ?? 'amount';
        $this->coverageValue = $scheme->coverage_value !== null ? (string) $scheme->coverage_value : '';
        $this->description = (string) $scheme->description;
        $this->isActive = (bool) $scheme->is_active;
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->resetFormFields();
        $this->editingId = null;
        $this->showForm = false;
    }

    public function saveScheme(): void
    {
        $this->guardManage();
        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:40'],
            'provider' => ['nullable', 'string', 'max:150'],
            'coverageType' => ['required', 'in:'.implode(',', Scheme::COVERAGE_TYPES)],
            'coverageValue' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $data = [
            'name' => $this->name,
            'code' => $this->code ?: null,
            'provider' => $this->provider ?: null,
            'coverage_type' => $this->coverageType,
            'coverage_value' => $this->coverageValue !== '' ? (float) $this->coverageValue : null,
            'description' => $this->description ?: null,
            'is_active' => $this->isActive,
        ];

        if ($this->editingId === null) {
            $data['tenant_id'] = auth()->user()->tenant_id;
            $scheme = Scheme::create($data);
            session()->flash('message', 'Scheme "'.$scheme->name.'" added.');
        } else {
            $scheme = Scheme::findOrFail($this->editingId);
            $scheme->update($data);
            session()->flash('message', 'Scheme "'.$scheme->name.'" updated.');
        }
        $this->cancelForm();
    }

    public function deleteScheme(int $id): void
    {
        $this->guardManage();
        $scheme = Scheme::findOrFail($id);
        if ($scheme->patients()->exists() || $scheme->appointments()->exists()) {
            session()->flash('error', 'Patients are enrolled in this scheme, so it cannot be deleted.');
            return;
        }
        $scheme->delete();
        session()->flash('message', 'Scheme removed.');
    }

    public function openIncome(int $id): void
    {
        $this->guardManage();
        $this->incomeSchemeId = $id;
        $this->incomeAmount = '';
        $this->incomeNote = '';
        $this->showIncome = true;
    }

    public function cancelIncome(): void
    {
        $this->showIncome = false;
        $this->incomeSchemeId = null;
        $this->incomeAmount = '';
        $this->incomeNote = '';
    }

    public function recordIncome(): void
    {
        $this->guardManage();
        $this->validate([
            'incomeAmount' => ['required', 'numeric', 'min:0.01'],
            'incomeNote' => ['nullable', 'string', 'max:500'],
        ]);

        $scheme = Scheme::findOrFail($this->incomeSchemeId);

        app(Accounting::class)->bookIncome(
            'Scheme grant · '.$scheme->name,
            (float) $this->incomeAmount,
            'scheme_grant',
            $scheme->id,
            $this->incomeNote ?: 'Government amount received under '.$scheme->name,
            auth()->user()
        );

        session()->flash('message', 'Income of Rs '.number_format((float) $this->incomeAmount, 2).' booked under "'.$scheme->name.'".');
        $this->cancelIncome();
    }

    protected function resetFormFields(): void
    {
        $this->name = '';
        $this->code = '';
        $this->provider = '';
        $this->coverageType = 'amount';
        $this->coverageValue = '';
        $this->description = '';
        $this->isActive = true;
    }

    protected function guardManage(): void
    {
        abort_unless(hms_can('schemes.manage'), 403);
    }

    public function render()
    {
        abort_unless(hms_can('schemes'), 403);

        $schemes = Scheme::query()
            ->withCount('patients')
            ->when($this->search !== '', function ($q) {
                $term = '%'.mb_strtolower($this->search).'%';
                $q->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(code) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(provider) LIKE ?', [$term]);
                });
            })
            ->when($this->active !== '', fn ($q) => $q->where('is_active', $this->active === '1'))
            ->orderBy('name')
            ->paginate(15);

        $incomeScheme = $this->incomeSchemeId ? Scheme::find($this->incomeSchemeId) : null;

        return view('livewire.admins.schemes', [
            'schemes' => $schemes,
            'canManage' => hms_can('schemes.manage'),
            'incomeScheme' => $incomeScheme,
        ]);
    }
}