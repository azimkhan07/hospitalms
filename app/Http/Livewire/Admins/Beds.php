<?php

namespace App\Http\Livewire\Admins;

use App\Models\Tenant;
use App\Models\beds as ModelsBeds;
use App\Models\patient;
use App\Models\rooms;
use App\Models\stay;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('admins.layouts.app')]
class Beds extends Component
{
    /**
     * Accommodation sections, in the order they are shown on the bed map.
     * A private room is never mixed in with the general ward because it is
     * billed separately and never shared (PLAN.md section 9b).
     */
    public const SECTIONS = [
        'general' => ['label' => 'General Ward', 'prefix' => 'G', 'private' => false],
        'ward' => ['label' => 'Ward', 'prefix' => 'W', 'private' => false],
        'icu' => ['label' => 'ICU', 'prefix' => 'ICU', 'private' => false],
        'private' => ['label' => 'Private Rooms', 'prefix' => 'P', 'private' => true],
        'semi-private' => ['label' => 'Semi-Private Rooms', 'prefix' => 'SP', 'private' => false],
    ];

    public string $_page = 'index';

    public ?int $room_id = null;

    public ?int $patient_id = null;

    public string $alloted_time = '';

    public string $discharge_time = '';

    public string $bed_number = '';

    /**
     * Patient picked per bed number in the allocation dropdown, keyed by bed id.
     */
    public array $allocatePatient = [];

    public ?int $edit_bed_id = null;

    public string $button_text = 'Add New Bed';

    public function mount(): void
    {
        $this->_page = 'index';
    }

    /**
     * Read-only means: no room setup, no allocation, no status change. The
     * admin still sees the whole bed map (PLAN.md section 9b.5).
     */
    public function canManage(): bool
    {
        return hms_can('beds.manage');
    }

    public function canAllocate(): bool
    {
        return hms_can('beds.allocate');
    }

    public function canChangeStatus(): bool
    {
        return hms_can('beds.status');
    }

    /**
     * Is private accommodation switched on for this facility? Answered by the
     * Super Admin at hospital creation; always off for a clinic.
     */
    public function hasPrivateRooms(): bool
    {
        $tenant = auth()->user()?->tenant;

        return (bool) $tenant?->hasPrivateRooms();
    }

    public function show_create_form(): void
    {
        $this->authorizeAction('beds.manage');
        $this->_page = 'create';
    }

    public function show_edit_form($id): void
    {
        $this->authorizeAction('beds.manage');
        $this->_page = 'edit';
        $bed = ModelsBeds::findOrFail($id);
        $this->edit_bed_id = $bed->id;
        $this->room_id = $bed->room_id;
        $this->patient_id = $bed->patient_id;
        $this->alloted_time = (string) ($bed->alloted_time ?? '');
        $this->bed_number = (string) $bed->bed_number;
        $this->button_text = 'Update Bed';
    }

    public function show_index(): void
    {
        $this->_page = 'index';
    }

    /**
     * Guard a mutating action. The bed map is visible to the admin, but every
     * write path is refused for them.
     */
    protected function authorizeAction(string $permission): void
    {
        abort_unless(hms_can($permission), 403);
    }

    public function add_bed(): void
    {
        if ($this->edit_bed_id) {
            $this->update($this->edit_bed_id);

            return;
        }

        $this->authorizeAction('beds.manage');
        $this->validate([
            'room_id' => 'required|numeric',
            'bed_number' => 'required|string|max:20',
            'alloted_time' => 'required',
        ]);

        ModelsBeds::create([
            'tenant_id' => auth()->user()->tenant_id,
            'room_id' => $this->room_id,
            'bed_number' => $this->bed_number,
            'patient_id' => $this->patient_id,
            'alloted_time' => $this->alloted_time,
            'status' => $this->patient_id ? 'alloted' : 'available',
        ]);

        $this->resetFormFields();
        session()->flash('message', 'Bed created successfully.');
        $this->_page = 'index';
    }

    public function update($id): void
    {
        $this->authorizeAction('beds.manage');
        $this->validate([
            'room_id' => 'required|numeric',
            'bed_number' => 'required|string|max:20',
            'patient_id' => 'nullable|numeric',
            'alloted_time' => 'nullable',
            'discharge_time' => 'nullable',
        ]);

        $bed = ModelsBeds::findOrFail($id);
        $bed->room_id = $this->room_id;
        $bed->bed_number = $this->bed_number;
        $bed->patient_id = $this->patient_id ?: null;
        $bed->alloted_time = $this->alloted_time ?: null;
        $bed->discharge_time = $this->discharge_time ?: null;
        $bed->status = $bed->patient_id ? 'alloted' : 'available';

        $bed->save();

        $this->resetFormFields();
        $this->button_text = 'Add New Bed';
        session()->flash('message', 'Bed updated successfully.');
        $this->_page = 'index';
    }

    /**
     * Hand a free bed to a patient. Owned by the Dean; the receptionist runs it
     * at the counter. The admin may not allocate (PLAN.md section 9b.5).
     */
    public function allocate(int $bedId): void
    {
        $this->authorizeAction('beds.allocate');

        $patientId = (int) ($this->allocatePatient[$bedId] ?? 0);

        if (! $patientId) {
            session()->flash('error', 'Pick a patient first.');

            return;
        }

        $bed = ModelsBeds::findOrFail($bedId);

        if (! $bed->isAllocatable()) {
            session()->flash('error', 'That bed is not free.');

            return;
        }

        $bed->update([
            'patient_id' => $patientId,
            'status' => 'alloted',
            'alloted_time' => now(),
            'discharge_time' => null,
        ]);

        // Allocation opens the stay the ward screen and discharge history
        // read - without it an admitted patient leaves no IPD record at all.
        $openStay = stay::where('bed_id', $bed->id)->where('status', 'active')->first()
            ?? stay::where('patient_id', $patientId)->where('status', 'active')->first();

        if (! $openStay) {
            stay::create([
                'patient_id' => $patientId,
                'room_id' => $bed->room_id,
                'bed_id' => $bed->id,
                'start_time' => now()->timestamp,
                'status' => 'active',
                'amount' => 0,
                'discount' => 0,
                'total' => 0,
            ]);
        } else {
            $openStay->update([
                'patient_id' => $patientId,
                'room_id' => $bed->room_id,
                'bed_id' => $bed->id,
            ]);
        }

        unset($this->allocatePatient[$bedId]);

        session()->flash('message', 'Bed '.$bed->label().' allocated.');
    }

    /**
     * Free a bed. A discharge releases exactly the one numbered bed, not the
     * whole room.
     */
    public function release(int $bedId): void
    {
        $this->authorizeAction('beds.allocate');

        $bed = ModelsBeds::findOrFail($bedId);

        // Close the open stay first, while the bed still remembers its patient.
        $openStay = stay::where('bed_id', $bed->id)->where('status', 'active')->first()
            ?? ($bed->patient_id
                ? stay::where('patient_id', $bed->patient_id)->where('status', 'active')->first()
                : null);

        $bed->update([
            'patient_id' => null,
            'status' => 'cleaning',
            'discharge_time' => now(),
        ]);

        if ($openStay) {
            $openStay->update([
                'status' => 'completed',
                'end_time' => now()->timestamp,
                'discharged_at' => now(),
                'discharge_type' => $openStay->discharge_type ?: 'normal',
                'discharged_by' => auth()->id(),
            ]);
        }

        session()->flash('message', 'Bed '.$bed->label().' released, pending cleaning.');
    }

    /**
     * Nurse action: mark a bed as cleaned and back in service, or out of order.
     */
    public function setStatus(int $bedId, string $status): void
    {
        $this->authorizeAction('beds.status');

        abort_unless(in_array($status, ['available', 'cleaning', 'maintenance'], true), 422);

        $bed = ModelsBeds::findOrFail($bedId);

        if ($bed->patient_id && $status !== 'available') {
            session()->flash('error', 'This bed still has a patient in it.');

            return;
        }

        $bed->update(['status' => $status]);
        session()->flash('message', 'Bed '.$bed->label().' marked as '.$status.'.');
    }

    public function delete($id): void
    {
        $this->authorizeAction('beds.manage');
        ModelsBeds::findOrFail($id)->delete();
        session()->flash('message', 'Bed deleted successfully.');
        $this->resetFormFields();
    }

    protected function resetFormFields(): void
    {
        $this->room_id = null;
        $this->patient_id = null;
        $this->alloted_time = '';
        $this->discharge_time = '';
        $this->bed_number = '';
        $this->edit_bed_id = null;
    }

    /**
     * The bed map, grouped by accommodation section. Rooms are loaded with
     * their beds in one query rather than one query per room.
     */
    public function sections()
    {
        $query = rooms::with(['beds' => fn ($q) => $q->with('patient')])
            ->orderBy('floor')
            ->orderBy('name');

        $grouped = $query->get()->groupBy('type');

        $out = [];

        foreach (self::SECTIONS as $type => $meta) {
            // A clinic has no in-patient accommodation at all, and a hospital
            // that answered "No" at creation gets no private section either.
            if ($type === 'private' && ! $this->hasPrivateRooms()) {
                continue;
            }

            $roomsForType = $grouped->get($type, collect());

            $total = $roomsForType->sum(fn ($r) => $r->beds->count());
            $occupied = $roomsForType->sum(fn ($r) => $r->beds->where('status', 'alloted')->count());

            $out[$type] = [
                'label' => $meta['label'],
                'rooms' => $roomsForType,
                'total' => $total,
                'occupied' => $occupied,
                'free' => $total - $occupied,
            ];
        }

        return $out;
    }

    public function render()
    {
        abort_unless(hms_can('beds'), 403);

        if ($this->_page === 'create' || $this->_page === 'edit') {
            return view('livewire.admins.beds.create', [
                'patients' => patient::orderBy('name')->get(),
                'rooms' => rooms::orderBy('name')->get(),
            ]);
        }

        return view('livewire.admins.beds.index', [
            'sections' => $this->sections(),
            'rooms' => rooms::orderBy('name')->get(),
            'patients' => patient::orderBy('name')->get(),
        ]);
    }
}