<?php

namespace App\Http\Livewire\Admins;

use App\Models\beds;
use App\Models\Concerns\BelongsToTenant;
use App\Models\InvestigationReport;
use App\Models\Machine;
use App\Models\rooms;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('admins.layouts.app')]

/**
 * Everything recorded against one bed or one room (PLAN.md 9d.3).
 *
 * A doctor picks a bed number from the live bed map and gets the whole picture
 * in one place: the investigations with their calculated charges, the machines
 * used there, and the bill. The same view is the printable sheet.
 */
class BedReports extends Component
{
    public string $placement = 'bed';

    public string $bedId = '';

    public string $roomId = '';

    public string $search = '';

    /** A nurse or doctor may record a machine in the ward; nobody else may. */
    public bool $showMachineForm = false;

    public string $machineName = '';

    public string $machineModality = '';

    public string $machineLocation = '';

    public ?int $machineBedId = null;

    public ?int $machineRoomId = null;

    public function pickBed(string $id): void
    {
        $this->bedId = $id;
        $this->roomId = '';
        $this->placement = 'bed';

        // Keep the ward machine form pointed at the bed that is on screen. The
        // form used to read this off a hidden input, but wire:model never syncs
        // a hidden field (it only listens for user events), so the machine was
        // saved with no bed at all.
        $this->machineBedId = (int) $id;
        $this->machineRoomId = null;
    }

    public function pickRoom(string $id): void
    {
        $this->roomId = $id;
        $this->bedId = '';
        $this->placement = 'room';
        $this->machineRoomId = (int) $id;
        $this->machineBedId = null;
    }

    public function clearSelection(): void
    {
        $this->bedId = '';
        $this->roomId = '';
        $this->machineBedId = null;
        $this->machineRoomId = null;
    }

    /**
     * In the ICU a machine is added by whoever is standing at the bedside, so
     * the nurse and the doctor may do it without waiting for the Dean's setup
     * round. Only a working machine can be added.
     */
    public function createMachine(): void
    {
        abort_unless(hms_can('machines.manage') || $this->inWard(), 403);

        $this->validate([
            'machineName' => ['required', 'string', 'max:150'],
            'machineModality' => ['nullable', 'string', 'max:80'],
            'machineLocation' => ['nullable', 'string', 'max:150'],
            'machineBedId' => ['nullable', 'integer'],
            'machineRoomId' => ['nullable', 'integer'],
        ]);

        Machine::create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $this->machineName,
            'modality' => $this->machineModality ?: null,
            'location' => $this->machineLocation ?: null,
            'status' => 'working',
            'rate' => 0,
            'bed_id' => $this->machineBedId ?: null,
            'room_id' => $this->machineRoomId ?: null,
        ]);

        session()->flash('message', 'Machine "'.$this->machineName.'" recorded in the ward.');

        $this->machineName = '';
        $this->machineModality = '';
        $this->machineLocation = '';
        $this->showMachineForm = false;
    }

    /** Doctors and nurses work in the ward; the Dean oversees it. */
    protected function inWard(): bool
    {
        // The permission list already carries the roles that work in a ward
        // (doctor, nurse, and the admin who reads it), so this is just
        // "may read ward reports" -- asking whether the tenant ticks 'doctor'
        // instead would refuse a nurse at a facility that has not ticked it yet.
        return hms_can('bedreports');
    }

    public function render()
    {
        abort_unless(hms_can('bedreports'), 403);

        $bed = $this->bedId !== '' ? beds::find($this->bedId) : null;
        $room = $this->roomId !== '' ? rooms::find($this->roomId) : null;

        $query = InvestigationReport::query()
            ->with(['test:id,name,code,calc_type', 'machine:id,name,modality', 'bed:id,bed_number', 'room:id,name'])
            ->when($bed, fn ($q) => $q->where('bed_id', $bed->id))
            ->when($room, fn ($q) => $q->where('room_id', $room->id))
            ->when(! $bed && ! $room, fn ($q) => $q->whereRaw('1=0'))
            ->when($this->search !== '', function ($q) {
                $term = '%'.mb_strtolower($this->search).'%';
                $q->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(findings) LIKE ?', [$term])
                        ->orWhereHas('test', fn ($t) => $t->whereRaw('LOWER(name) LIKE ?', [$term]));
                });
            })
            ->orderByDesc('created_at');

        $reports = $query->get();

        $machines = Machine::query()
            ->with(['room:id,name', 'bed:id,bed_number'])
            ->when($bed, fn ($q) => $q->where('bed_id', $bed->id))
            ->when($room, fn ($q) => $q->where('room_id', $room->id))
            ->when(! $bed && ! $room, fn ($q) => $q->whereRaw('1=0'))
            ->orderBy('name')
            ->get();

        return view('livewire.admins.bed-reports', [
            'bed' => $bed,
            'room' => $room,
            'reports' => $reports,
            'machines' => $machines,
            'beds' => beds::orderBy('bed_number')->get(['id', 'bed_number', 'status']),
            'rooms' => rooms::orderBy('name')->get(['id', 'name', 'status']),
            'total' => $reports->sum(fn ($r) => (float) $r->charge),
            'canAddMachine' => hms_can('machines.manage') || $this->inWard(),
        ]);
    }
}