<?php

namespace App\Http\Livewire\Admins;

use App\Models\appointment;
use App\Models\medicine;
use App\Models\patient;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('admins.layouts.app')]
class Prescriptions extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public string $search = '';

    public bool $showForm = false;

    public ?int $patientId = null;

    /** The OPD visit this script belongs to, handed over from Consultations. */
    #[Url]
    public string $appointment = '';

    public string $notes = '';

    public array $items = [];

    public function mount(): void
    {
        $this->items = [$this->blankItem()];

        if ($this->appointment !== '') {
            $appt = appointment::find((int) $this->appointment);

            if ($appt) {
                $this->patientId = $appt->patient_id;
                $this->showForm = true;
            }
        }
    }

    private function blankItem(): array
    {
        return ['medicine' => '', 'dosage' => '', 'frequency' => '', 'duration' => '', 'quantity' => '', 'note' => ''];
    }

    public function render()
    {
        if (! hms_can('prescriptions')) {
            abort(403);
        }

        $prescriptions = Prescription::with(['patient:id,name', 'doctor:id,name', 'items'])
            ->when($this->search !== '', function ($q) {
                $term = '%'.mb_strtolower($this->search).'%';
                $q->where(function ($q) use ($term) {
                    $q->whereHas('patient', fn ($p) => $p->whereRaw('LOWER(name) LIKE ?', [$term]))
                        ->orWhereHas('doctor', fn ($d) => $d->whereRaw('LOWER(name) LIKE ?', [$term]))
                        ->orWhereHas('items', fn ($i) => $i->whereRaw('LOWER(medicine) LIKE ?', [$term]));
                });
            })
            ->orderByDesc('issued_at')
            ->paginate(12);

        return view('livewire.admins.prescriptions', [
            'prescriptions' => $prescriptions,
            'patients' => patient::orderBy('name')->limit(300)->get(),
            // The picker is stock-aware: only usable lines are selectable,
            // expired / out-of-stock lines show in red but cannot be chosen.
            'medicines' => medicine::usable()->orderBy('name')->limit(300)->get(),
            'unavailableMedicines' => medicine::whereNull('deleted_at')
                ->whereNotNull('name')
                ->where(function ($q) {
                    $q->where(function ($q) {
                        $q->whereNotNull('expiry_date')->whereDate('expiry_date', '<', today());
                    })->orWhere(function ($q) {
                        $q->whereNotNull('stock')->where('stock', '<=', 0);
                    });
                })
                ->orderBy('name')
                ->limit(200)
                ->get(),
            'doctors' => User::whereHas('role', fn ($r) => $r->whereIn('slug', ['doctor', 'admin']))
                ->orderBy('name')
                ->get(),
            'linkedAppointment' => $this->appointment !== ''
                ? appointment::with('patient:id,name')->find((int) $this->appointment)
                : null,
        ]);
    }

    public function addRow(): void
    {
        $this->items[] = $this->blankItem();
    }

    public function removeRow(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save(): void
    {
        $this->validate([
            'patientId' => 'required|exists:patients,id',
            'items' => 'required|array|min:1',
            'items.*.medicine' => 'required|max:180',
            'items.*.dosage' => 'nullable|max:120',
            'items.*.frequency' => 'nullable|max:120',
            'items.*.duration' => 'nullable|max:120',
            'items.*.quantity' => 'nullable|integer|min:1|max:9999',
            'items.*.note' => 'nullable|max:255',
            'notes' => 'nullable|max:1000',
        ], [
            'patientId.required' => 'Please choose a patient.',
            'patientId.exists' => 'That patient record no longer exists.',
            'items.required' => 'Add at least one medicine row.',
            'items.min' => 'Add at least one medicine row.',
            'items.*.medicine.required' => 'Medicine name is required on every row.',
        ]);

        $linkedAppointment = $this->appointment !== ''
            ? appointment::find((int) $this->appointment)
            : null;

        if ($linkedAppointment && (int) $linkedAppointment->patient_id !== (int) $this->patientId) {
            $linkedAppointment = null;
            $this->appointment = '';
        }

        DB::transaction(function () use ($linkedAppointment) {
            $prescription = Prescription::create([
                'patient_id' => $this->patientId,
                'doctor_id' => auth()->id(),
                'appointment_id' => $linkedAppointment?->id,
                'notes' => $this->notes ?: null,
                'status' => 'issued',
                'issued_at' => Carbon::now(),
            ]);

            foreach ($this->items as $item) {
                if (blank($item['medicine'] ?? null)) {
                    continue;
                }

                $prescription->items()->create([
                    'medicine' => $item['medicine'],
                    // Exact-name matches wire dispensing to the master; a free
                    // text line stays and is handed over without stock.
                    'medicine_id' => \App\Models\medicine::where('name', $item['medicine'])->value('id'),
                    'dosage' => $item['dosage'] ?: null,
                    'frequency' => $item['frequency'] ?: null,
                    'duration' => $item['duration'] ?: null,
                    // Blank means one course, the hand-over default.
                    'quantity' => $item['quantity'] ?: null,
                    'note' => $item['note'] ?: null,
                ]);
            }
        });

        $this->reset(['patientId', 'notes', 'items', 'showForm']);
        $this->items = [$this->blankItem()];

        session()->flash('success', 'Prescription issued.');
    }

    public function cancel(int $id): void
    {
        Prescription::findOrFail($id)->update(['status' => 'cancelled']);

        session()->flash('success', 'Prescription cancelled.');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
}