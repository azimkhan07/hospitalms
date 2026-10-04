<?php

namespace App\Http\Livewire\Admins;

use App\Models\beds;
use App\Models\department;
use App\Models\rooms as ModelsRooms;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('admins.layouts.app')]
class Rooms extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    /**
     * Bed-number prefix per accommodation kind, matching PLAN.md section 9b.
     */
    public const PREFIXES = [
        'general' => 'G',
        'ward' => 'W',
        'icu' => 'ICU',
        'private' => 'P',
        'semi-private' => 'SP',
    ];

    public $department;

    public $type;

    public $status = 'available';

    public $edit_Room_id;

    public $button_text = 'Add New Room';

    public $_page;

    public $name = '';

    public $floor = '';

    public $capacity = 1;

    public $daily_rate = '';

    public function mount()
    {
        $this->_page = 'index';
    }

    /**
     * Room setup is configuration the Dean owns. The admin may read the room
     * list but cannot create, edit or delete a room (PLAN.md section 9b.5).
     */
    public function canManage(): bool
    {
        return hms_can('beds.manage');
    }

    /**
     * How many private rooms this facility is allowed, as answered by the
     * Super Admin at hospital creation. Null means "not configured".
     */
    public function privateRoomQuota(): ?int
    {
        return auth()->user()?->tenant?->private_room_count;
    }

    public function hasPrivateRooms(): bool
    {
        return (bool) auth()->user()?->tenant?->hasPrivateRooms();
    }

    protected function authorizeManage(): void
    {
        abort_unless($this->canManage(), 403);
    }

    public function show_create_form()
    {
        $this->authorizeManage();
        $this->_page = 'create';
    }

    public function show_edit_form($id)
    {
        $this->authorizeManage();
        $this->_page = 'edit';
        $this->edit_Room_id = $id;
        $item = ModelsRooms::findOrFail($id);
        $this->department = $item->department_id;
        $this->type = $item->type;
        $this->status = $item->status;
        $this->name = $item->name;
        $this->floor = $item->floor;
        $this->capacity = $item->capacity;
        $this->daily_rate = $item->daily_rate;
        $this->button_text = 'Update Room';
    }

    public function show_index()
    {
        $this->_page = 'index';
    }

    public function add_room()
    {
        if ($this->edit_Room_id) {
            $this->update($this->edit_Room_id);

            return;
        }

        $this->authorizeManage();
        $this->validate($this->rules());

        // A private room holds exactly one patient, so capacity is fixed at 1.
        $capacity = $this->type === 'private' ? 1 : max(1, (int) $this->capacity);

        $room = ModelsRooms::create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $this->name,
            'floor' => $this->floor ?: null,
            'department_id' => $this->department,
            'type' => $this->type,
            'capacity' => $capacity,
            'daily_rate' => $this->daily_rate !== '' ? (float) $this->daily_rate : null,
            'status' => $this->status,
        ]);

        $this->createBeds($room, $capacity);

        $this->resetFormFields();
        session()->flash('message', 'Room created with '.$capacity.' numbered bed(s).');
        $this->_page = 'index';
    }

    public function update($id)
    {
        $this->authorizeManage();
        $this->validate($this->rules());

        $room = ModelsRooms::findOrFail($id);
        $capacity = $this->type === 'private' ? 1 : max(1, (int) $this->capacity);

        $room->update([
            'name' => $this->name,
            'floor' => $this->floor ?: null,
            'department_id' => $this->department,
            'type' => $this->type,
            'capacity' => $capacity,
            'daily_rate' => $this->daily_rate !== '' ? (float) $this->daily_rate : null,
            'status' => $this->status,
        ]);

        // Grow the room only. Never renumber or remove beds that exist, because
        // a bed number is referenced by a stay and a discharge must free
        // exactly that one bed.
        $needed = $capacity - $room->beds()->count();
        if ($needed > 0) {
            $this->createBeds($room->fresh(), $needed);
        }

        $this->resetFormFields();
        $this->button_text = 'Add New Room';
        session()->flash('message', 'Room updated successfully.');
        $this->_page = 'index';
    }

    public function delete($id)
    {
        $this->authorizeManage();
        $room = ModelsRooms::findOrFail($id);
        $beds::where('room_id', $room->id)->delete();
        $room->delete();
        session()->flash('message', 'Room deleted successfully.');
        $this->resetFormFields();
        $this->button_text = 'Add New Room';
    }

    /**
     * Create the numbered beds for a room. A ward or ICU gets several, a
     * private room exactly one.
     *
     * Numbers run per accommodation kind across the whole facility, not per
     * room, so the second private room is P2 rather than another P1
     * (PLAN.md section 9b).
     */
    protected function createBeds(ModelsRooms $room, int $count): void
    {
        $prefix = self::PREFIXES[$room->type] ?? 'G';
        $number = $this->nextBedNumber($room->type);

        for ($i = 0; $i < $count; $i++) {
            beds::firstOrCreate(
                ['room_id' => $room->id, 'bed_number' => $prefix.$number],
                ['tenant_id' => $room->tenant_id, 'status' => 'available'],
            );
            $number++;
        }
    }

    /**
     * Highest bed number already used for this accommodation kind, plus one.
     *
     * The prefix is stripped before casting because a bed number is text
     * ("P1", not 1), and casting "P1" straight to an int yields 0.
     */
protected function nextBedNumber(string $type): int
    {
        $max = ModelsRooms::with('beds')
            ->where('type', $type)
            ->get()
            ->flatMap(fn ($r) => $r->beds)
            ->map(fn ($b) => (int) preg_replace('/\D/', '', (string) $b->bed_number))
            ->filter(fn ($n) => $n > 0)
            ->max();

        return ((int) $max) + 1;
    }

    protected function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:100',
            'department' => 'required|numeric',
            'type' => ['required', Rule::in(array_keys(self::PREFIXES))],
            'status' => 'required|in:available,occupied,maintenance',
            'floor' => 'nullable|string|max:20',
            'capacity' => 'required|integer|min:1|max:200',
            'daily_rate' => 'nullable|numeric|min:0',
        ];

        // A facility that answered "No" to private rooms at creation cannot
        // quietly create one here.
        if ($this->type === 'private' && ! $this->hasPrivateRooms()) {
            $rules['type'] = ['prohibited'];
        }

        return $rules;
    }

    protected function resetFormFields(): void
    {
        $this->department = null;
        $this->type = null;
        $this->status = 'available';
        $this->edit_Room_id = null;
        $this->name = '';
        $this->floor = '';
        $this->capacity = 1;
        $this->daily_rate = '';
    }

    public function render()
    {
        abort_unless(hms_can('rooms'), 403);

        $sections = ModelsRooms::withCount('beds')->orderBy('floor')->orderBy('name')->get()
            ->groupBy('type');

        if ($this->_page == 'index') {
            return view('livewire.admins.rooms.index', [
                'sections' => $sections,
            ]);
        }

        return view('livewire.admins.rooms.create', [
            'departments' => department::orderBy('name')->get(),
            'privateQuota' => $this->privateRoomQuota(),
            'privateEnabled' => $this->hasPrivateRooms(),
        ]);
    }
}