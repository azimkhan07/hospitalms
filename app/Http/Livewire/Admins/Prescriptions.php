<?php

namespace App\Http\Livewire\Admins;

use App\Models\medicine;
use App\Models\patient;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
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

    public string $notes = '';

    public array $items = [];

    public function mount(): void
    {
        $this->items = [$this->blankItem()];
    }

    private function blankItem(): array
    {
        return ['medicine' => '', 'dosage' => '', 'frequency' => '', 'duration' => '', 'note' => ''];
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
            'medicines' => medicine::usable()->orderBy('name')->limit(300)->get(),
            'doctors' => User::whereHas('role', fn ($r) => $r->whereIn('slug', ['doctor', 'admin']))
                ->orderBy('name')
                ->get(),
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
            'items.*.note' => 'nullable|max:255',
            'notes' => 'nullable|max:1000',
        ], [
            'patientId.required' => 'Please choose a patient.',
            'patientId.exists' => 'That patient record no longer exists.',
            'items.required' => 'Add at least one medicine row.',
            'items.min' => 'Add at least one medicine row.',
            'items.*.medicine.required' => 'Medicine name is required on every row.',
        ]);

        DB::transaction(function () {
            $prescription = Prescription::create([
                'patient_id' => $this->patientId,
                'doctor_id' => auth()->id(),
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