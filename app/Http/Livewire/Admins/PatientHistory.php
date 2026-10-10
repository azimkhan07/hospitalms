<?php

namespace App\Http\Livewire\Admins;

use App\Models\patient;
use App\Models\Prescription;
use App\Models\stay;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('admins.layouts.app')]
class PatientHistory extends Component
{
    public string $search = '';

    public ?int $patientId = null;

    public function render()
    {
        if (! hms_can('history')) {
            abort(403);
        }

        $patients = patient::query()
            ->when($this->search !== '', function ($q) {
                $term = '%'.mb_strtolower($this->search).'%';
                $q->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(phone) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$term])
                        ->orWhereRaw('bloodgroup LIKE ?', [$term]);
                });
            })
            ->orderBy('name')
            ->limit(40)
            ->get();

        $selected = $this->patientId
            ? patient::withCount([
                'appointments as appointments_count',
                'prescriptions as prescriptions_count',
                'stays as stays_count',
            ])->find($this->patientId)
            : null;

        $appointments = $selected
            ? \App\Models\appointment::with(['doctor.employ:id,name', 'icd10:id,code'])
                ->where('patient_id', $selected->id)
                ->orderByDesc('intime')
                ->limit(20)
                ->get()
            : collect();

        $prescriptions = $selected
            ? Prescription::with(['doctor:id,name', 'items'])
                ->where('patient_id', $selected->id)
                ->orderByDesc('issued_at')
                ->limit(20)
                ->get()
            : collect();

        $stays = $selected
            ? stay::with('room.department:id,name')
                ->where('patient_id', $selected->id)
                ->orderByDesc('start_time')
                ->limit(20)
                ->get()
            : collect();

        return view('livewire.admins.patient-history', [
            'patients' => $patients,
            'selected' => $selected,
            'appointments' => $appointments,
            'prescriptions' => $prescriptions,
            'stays' => $stays,
        ]);
    }

    public function select(int $id): void
    {
        $this->patientId = $id;
    }

    public function updatedSearch(): void
    {
        $this->patientId = null;
    }
}