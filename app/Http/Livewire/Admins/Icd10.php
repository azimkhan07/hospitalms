<?php

namespace App\Http\Livewire\Admins;

use App\Models\Icd10Code;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The ICD-10 diagnosis coding directory: the global baseline plus this
 * facility's own additions, searchable by code or description. Codes are
 * archived (is_active=false) rather than deleted so old consults keep their
 * meaning.
 */
#[Layout('admins.layouts.app')]
class Icd10 extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    #[Url]
    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $description = '';

    public string $chapter = '';

    public bool $isActive = true;

    public function mount(): void
    {
        if (! hms_can('staff')) {
            abort(403);
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $row = $this->codes()->findOrFail($id);

        $this->editingId = $row->id;
        $this->code = $row->code;
        $this->description = (string) $row->description;
        $this->chapter = (string) $row->chapter;
        $this->isActive = (bool) $row->is_active;
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $this->validate([
            'code' => ['required', 'string', 'max:10', Rule::unique('icd10_codes', 'code')->ignore($this->editingId)],
            'description' => ['required', 'string', 'max:300'],
            'chapter' => ['nullable', 'string', 'max:120'],
            'isActive' => ['boolean'],
        ]);

        $data = [
            'code' => strtoupper(trim($this->code)),
            'description' => $this->description,
            'chapter' => $this->chapter ?: null,
            'is_active' => $this->isActive,
        ];

        if ($this->editingId === null) {
            $data['tenant_id'] = auth()->user()->tenant_id;
            Icd10Code::create($data);
            session()->flash('message', 'ICD-10 code added.');
        } else {
            $this->codes()->findOrFail($this->editingId)->update($data);
            session()->flash('message', 'ICD-10 code updated.');
        }

        $this->resetForm();
    }

    /** Soft "delete": keep the code on old consults, hide it from new ones. */
    public function toggleActive(int $id): void
    {
        $row = $this->codes()->findOrFail($id);
        $row->update(['is_active' => ! $row->is_active]);

        session()->flash('message', $row->is_active ? 'Code restored.' : 'Code archived.');
    }

    private function resetForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->code = '';
        $this->description = '';
        $this->chapter = '';
        $this->isActive = true;
        $this->resetValidation();
    }

    private function codes()
    {
        return Icd10Code::query()
            ->forTenant(auth()->user()->tenant_id ? (int) auth()->user()->tenant_id : null)
            ->search($this->search)
            ->orderBy('code');
    }

    public function render()
    {
        return view('livewire.admins.icd10', [
            'codes' => $this->codes()->paginate(20),
        ]);
    }
}
