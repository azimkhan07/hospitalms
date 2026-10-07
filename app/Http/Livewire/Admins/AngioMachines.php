<?php

namespace App\Http\Livewire\Admins;

use App\Models\AngioMachine;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('admins.layouts.app')]
class AngioMachines extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = '';

    public bool $showForm = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $code = '';
    public string $manufacturer = '';
    public string $model = '';
    public string $serial_number = '';
    public string $vendor = '';
    public string $location = '';
    public string $installed_at = '';
    public string $machineStatus = 'working';
    public string $rate = '';
    public string $notes = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->status = '';
        $this->resetPage();
    }

    public function createMachine(): void
    {
        $this->guardManage();
        $this->resetFormFields();
        $this->editingId = null;
        $this->showForm = true;
    }

    public function editMachine(int $id): void
    {
        $this->guardManage();
        $machine = AngioMachine::findOrFail($id);
        $this->editingId = $machine->id;
        $this->name = (string) $machine->name;
        $this->code = (string) $machine->code;
        $this->manufacturer = (string) $machine->manufacturer;
        $this->model = (string) $machine->model;
        $this->serial_number = (string) $machine->serial_number;
        $this->vendor = (string) $machine->vendor;
        $this->location = (string) $machine->location;
        $this->installed_at = $machine->installed_at?->format('Y-m-d') ?? '';
        $this->machineStatus = $machine->status ?? 'working';
        $this->rate = $machine->rate !== null ? (string) $machine->rate : '';
        $this->notes = (string) $machine->notes;
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->resetFormFields();
        $this->editingId = null;
        $this->showForm = false;
    }

    public function saveMachine(): void
    {
        $this->guardManage();
        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:40'],
            'manufacturer' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'vendor' => ['nullable', 'string', 'max:150'],
            'location' => ['nullable', 'string', 'max:150'],
            'installed_at' => ['nullable', 'date'],
            'machineStatus' => ['required', 'in:'.implode(',', AngioMachine::STATUSES)],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $data = [
            'name' => $this->name,
            'code' => $this->code ?: null,
            'manufacturer' => $this->manufacturer ?: null,
            'model' => $this->model ?: null,
            'serial_number' => $this->serial_number ?: null,
            'vendor' => $this->vendor ?: null,
            'location' => $this->location ?: null,
            'installed_at' => $this->installed_at ?: null,
            'status' => $this->machineStatus,
            'rate' => $this->rate !== '' ? (float) $this->rate : null,
            'notes' => $this->notes ?: null,
        ];

        if ($this->editingId === null) {
            $data['tenant_id'] = auth()->user()->tenant_id;
            $machine = AngioMachine::create($data);
            session()->flash('message', 'Angio machine "'.$machine->name.'" added.');
        } else {
            $machine = AngioMachine::findOrFail($this->editingId);
            $machine->update($data);
            session()->flash('message', 'Angio machine "'.$machine->name.'" updated.');
        }
        $this->cancelForm();
    }

    public function deleteMachine(int $id): void
    {
        $this->guardManage();
        $machine = AngioMachine::findOrFail($id);
        if ($machine->appointments()->exists()) {
            session()->flash('error', 'This angio machine already has treatments against it, so it can only be retired.');
            return;
        }
        $machine->delete();
        session()->flash('message', 'Angio machine removed.');
    }

    protected function resetFormFields(): void
    {
        $this->name = '';
        $this->code = '';
        $this->manufacturer = '';
        $this->model = '';
        $this->serial_number = '';
        $this->vendor = '';
        $this->location = '';
        $this->installed_at = '';
        $this->machineStatus = 'working';
        $this->rate = '';
        $this->notes = '';
    }

    protected function guardManage(): void
    {
        abort_unless(hms_can('angio.manage'), 403);
    }

    public function render()
    {
        abort_unless(hms_can('angio'), 403);

        $machines = AngioMachine::query()
            ->withCount('appointments')
            ->when($this->search !== '', function ($q) {
                $term = '%'.mb_strtolower($this->search).'%';
                $q->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(code) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(manufacturer) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(model) LIKE ?', [$term]);
                });
            })
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.admins.angio-machines', [
            'machines' => $machines,
            'canManage' => hms_can('angio.manage'),
            'statuses' => AngioMachine::STATUSES,
        ]);
    }
}