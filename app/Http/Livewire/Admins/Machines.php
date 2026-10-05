<?php

namespace App\Http\Livewire\Admins;

use App\Models\beds;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Machine;
use App\Models\rooms;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('admins.layouts.app')]

/**
 * The machine master (PLAN.md 9d.1).
 *
 * The Dean sets machines up; the admin reads the list. A machine can be pinned
 * to a room or a bed, which is what makes the ICU report able to answer "what
 * was used on bed 12" without anyone retyping it.
 */
class Machines extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $modality = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $code = '';

    public string $modalityChoice = '';

    public string $department = '';

    public string $location = '';

    public string $serial_number = '';

    public string $vendor = '';

    public string $purchase_date = '';

    public string $warranty_ends_at = '';

    public string $machineStatus = 'working';

    public string $rate = '0';

    public ?int $room_id = null;

    public ?int $bed_id = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->status = '';
        $this->modality = '';
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
        $machine = Machine::findOrFail($id);

        $this->editingId = $machine->id;
        $this->name = (string) $machine->name;
        $this->code = (string) $machine->code;
        $this->modalityChoice = (string) $machine->modality;
        $this->department = (string) $machine->department;
        $this->location = (string) $machine->location;
        $this->serial_number = (string) $machine->serial_number;
        $this->vendor = (string) $machine->vendor;
        $this->purchase_date = $machine->purchase_date?->format('Y-m-d') ?? '';
        $this->warranty_ends_at = $machine->warranty_ends_at?->format('Y-m-d') ?? '';
        $this->machineStatus = (string) $machine->status;
        $this->rate = (string) $machine->rate;
        $this->room_id = $machine->room_id;
        $this->bed_id = $machine->bed_id;
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
            'code' => ['nullable', 'string', 'max:40', Rule::unique('machines', 'code')->ignore($this->editingId)],
            'modalityChoice' => ['nullable', 'string', 'max:80'],
            'department' => ['nullable', 'string', 'max:150'],
            'location' => ['nullable', 'string', 'max:150'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'vendor' => ['nullable', 'string', 'max:150'],
            'purchase_date' => ['nullable', 'date'],
            'warranty_ends_at' => ['nullable', 'date'],
            'machineStatus' => ['required', Rule::in(Machine::STATUSES)],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'room_id' => ['nullable', 'integer'],
            'bed_id' => ['nullable', 'integer'],
        ]);

        $data = [
            'name' => $this->name,
            'code' => $this->code ?: null,
            'modality' => $this->modalityChoice ?: null,
            'department' => $this->department ?: null,
            'location' => $this->location ?: null,
            'serial_number' => $this->serial_number ?: null,
            'vendor' => $this->vendor ?: null,
            'purchase_date' => $this->purchase_date ?: null,
            'warranty_ends_at' => $this->warranty_ends_at ?: null,
            'status' => $this->machineStatus,
            'rate' => (float) ($this->rate ?: 0),
            'room_id' => $this->room_id ?: null,
            'bed_id' => $this->bed_id ?: null,
        ];

        if ($this->editingId === null) {
            $data['tenant_id'] = auth()->user()->tenant_id;

            $machine = Machine::create($data);
            session()->flash('message', 'Machine "'.$machine->name.'" added.');
        } else {
            $machine = Machine::findOrFail($this->editingId);
            $machine->update($data);
            session()->flash('message', 'Machine "'.$machine->name.'" updated.');
        }

        $this->cancelForm();
    }

    public function deleteMachine(int $id): void
    {
        $this->guardManage();
        $machine = Machine::findOrFail($id);

        if ($machine->reports()->exists()) {
            session()->flash('error', 'This machine already has investigations against it, so it can only be retired.');

            return;
        }

        $machine->delete();
        session()->flash('message', 'Machine removed.');
    }

    protected function resetFormFields(): void
    {
        $this->name = '';
        $this->code = '';
        $this->modalityChoice = '';
        $this->department = '';
        $this->location = '';
        $this->serial_number = '';
        $this->vendor = '';
        $this->purchase_date = '';
        $this->warranty_ends_at = '';
        $this->machineStatus = 'working';
        $this->rate = '0';
        $this->room_id = null;
        $this->bed_id = null;
    }

    /** Machines are the Dean's setup job; the admin only reads the list. */
    protected function guardManage(): void
    {
        abort_unless(hms_can('machines.manage'), 403);
    }

    public function render()
    {
        abort_unless(hms_can('machines'), 403);

        $machines = Machine::query()
            ->with(['room:id,name', 'bed:id,bed_number'])
            ->when($this->search !== '', function ($q) {
                $term = '%'.mb_strtolower($this->search).'%';
                $q->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(code) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(vendor) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(serial_number) LIKE ?', [$term]);
                });
            })
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->modality !== '', fn ($q) => $q->where('modality', $this->modality))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.admins.machines', [
            'machines' => $machines,
            'canManage' => hms_can('machines.manage'),
            'modalities' => Machine::MODALITIES,
            'statuses' => Machine::STATUSES,
            'rooms' => rooms::orderBy('name')->get(['id', 'name']),
            'beds' => beds::orderBy('bed_number')->get(['id', 'bed_number']),
        ]);
    }
}