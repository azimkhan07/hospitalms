<?php

namespace App\Http\Livewire\Admins;

use App\Models\beds;
use App\Models\patient;
use App\Models\stay;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The IPD ward (PLAN.md section 7): who is admitted and where, the admission
 * hand-off, vitals and the discharge record. Admitting occupies a bed and
 * opens a stay; discharging closes the stay and frees the bed for cleaning.
 */
#[Layout('admins.layouts.app')]
class Ward extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    #[Url]
    public string $search = '';

    #[Url]
    public string $tab = 'active';

    public $admitPatientId = '';

    public $admitBedId = '';

    public ?int $dischargeStayId = null;

    public string $dischargeType = 'recovered';

    public string $dischargeNote = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTab(): void
    {
        $this->resetPage();
    }

    public function admit(): void
    {
        if (! hms_can('beds.allocate')) {
            abort(403);
        }

        $this->validate([
            'admitPatientId' => 'required|exists:patients,id',
            'admitBedId' => 'required|exists:beds,id',
        ], [
            'admitPatientId.required' => 'Pick the patient being admitted.',
            'admitBedId.required' => 'Pick a free bed.',
        ]);

        $bed = beds::findOrFail((int) $this->admitBedId);

        if (! $bed->isAllocatable()) {
            session()->flash('error', 'That bed is not free any more.');

            return;
        }

        // One stay per admission: this is what DischargeHistory reads.
        stay::create([
            'patient_id' => (int) $this->admitPatientId,
            'room_id' => $bed->room_id,
            'bed_id' => $bed->id,
            'start_time' => now()->timestamp,
            'status' => 'active',
            'amount' => 0,
            'discount' => 0,
            'total' => 0,
        ]);

        $bed->update([
            'patient_id' => (int) $this->admitPatientId,
            'status' => 'alloted',
            'alloted_time' => now(),
            'discharge_time' => null,
        ]);

        $this->reset(['admitPatientId', 'admitBedId']);
        session()->flash('message', 'Patient admitted to '.$bed->label().'.');
    }

    public function openDischarge(int $stayId): void
    {
        if (! hms_can('ward.discharge')) {
            abort(403);
        }

        $stay = stay::findOrFail($stayId);

        $this->dischargeStayId = $stay->id;
        $this->dischargeType = $stay->discharge_type ?: 'recovered';
        $this->dischargeNote = (string) $stay->discharge_note;
        $this->resetValidation();
    }

    public function closeDischarge(): void
    {
        $this->dischargeStayId = null;
    }

    public function discharge(): void
    {
        if (! hms_can('ward.discharge')) {
            abort(403);
        }

        $this->validate([
            'dischargeStayId' => 'required|integer',
            'dischargeType' => 'required|in:'.implode(',', array_keys(stay::DISCHARGE_TYPES)),
            'dischargeNote' => 'nullable|string|max:2000',
        ]);

        $stay = stay::findOrFail($this->dischargeStayId);

        $stay->update([
            'status' => 'completed',
            'end_time' => now()->timestamp,
            'discharged_at' => now(),
            'discharge_type' => $this->dischargeType,
            'discharge_note' => $this->dischargeNote ?: null,
            'discharged_by' => auth()->id(),
        ]);

        // Free the bed exactly like the reception counter's release does.
        if ($stay->bed_id) {
            beds::where('id', $stay->bed_id)->update([
                'patient_id' => null,
                'status' => 'cleaning',
                'discharge_time' => now(),
            ]);
        }

        $this->dischargeStayId = null;
        $this->dischargeNote = '';
        session()->flash('message', 'Patient discharged, bed released for cleaning.');
    }

    public function goVitals(int $stayId): void
    {
        $this->redirect(route('admin_vitals', ['stay' => $stayId]));
    }

    public function render()
    {
        if (! hms_can('ward')) {
            abort(403);
        }

        $query = stay::with([
            'patient:id,name,age,gender,bloodgroup,phone',
            'room:id,name',
            'bed:id,bed_number,room_id',
            'latestVital',
        ]);

        $query->when($this->search !== '', function ($q) {
            $term = '%'.mb_strtolower($this->search).'%';
            $q->whereHas('patient', fn ($p) => $p->whereRaw('LOWER(name) LIKE ?', [$term]));
        });

        if ($this->tab === 'active') {
            $stays = $query->where('status', 'active')->orderBy('start_time')->paginate(12);
        } else {
            $stays = $query->whereNotNull('discharged_at')->orderByDesc('discharged_at')->paginate(12);
        }

        return view('livewire.admins.ward', [
            'stays' => $stays,
            'canAdmit' => hms_can('beds.allocate'),
            'canDischarge' => hms_can('ward.discharge'),
            'patients' => patient::orderBy('name')->limit(300)->get(['id', 'name']),
            'freeBeds' => beds::with('room:id,name')
                ->where('status', 'available')
                ->whereNull('patient_id')
                ->get()
                ->map(fn ($b) => [
                    'id' => $b->id,
                    'label' => ($b->room?->name ?: 'Room #'.$b->room_id).' / Bed '.$b->label(),
                ]),
            'dischargeTypes' => stay::DISCHARGE_TYPES,
            'activeCount' => stay::where('status', 'active')->count(),
            'todayDischarges' => stay::whereDate('discharged_at', today())->count(),
        ]);
    }
}
