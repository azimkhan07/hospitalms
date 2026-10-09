<?php

namespace App\Http\Livewire\Admins;

use App\Models\appointment;
use App\Models\patient;
use App\Models\stay;
use App\Models\Vital;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Vital signs capture (PLAN.md section 6): reception records OPD readings at
 * check-in, the nurse records them on the ward, the doctor reviews them in
 * consultation. One screen, preselectable from any of those places.
 */
#[Layout('admins.layouts.app')]
class Vitals extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    #[Url]
    public string $appointment = '';

    #[Url]
    public string $stay = '';

    public $patientId = '';

    public $bpSystolic = '';

    public $bpDiastolic = '';

    public $pulse = '';

    public $temperature = '';

    public $spo2 = '';

    public $weight = '';

    public $height = '';

    public string $note = '';

    public function mount(): void
    {
        if ($this->appointment !== '') {
            $appt = appointment::find((int) $this->appointment);

            if ($appt) {
                $this->patientId = (string) $appt->patient_id;
            }
        }

        if ($this->stay !== '') {
            $row = stay::find((int) $this->stay);

            if ($row) {
                $this->patientId = (string) $row->patient_id;
            }
        }
    }

    /** Queue / bed-list chips: jump straight to that encounter. */
    public function useAppointment(int $id): void
    {
        $appt = appointment::findOrFail($id);

        $this->appointment = (string) $id;
        $this->stay = '';
        $this->patientId = (string) $appt->patient_id;
        $this->resetReadings();
    }

    public function useStay(int $id): void
    {
        $row = stay::findOrFail($id);

        $this->stay = (string) $id;
        $this->appointment = '';
        $this->patientId = (string) $row->patient_id;
        $this->resetReadings();
    }

    private function resetReadings(): void
    {
        $this->reset([
            'bpSystolic', 'bpDiastolic', 'pulse', 'temperature',
            'spo2', 'weight', 'height', 'note',
        ]);
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->validate([
            'patientId' => 'required|exists:patients,id',
            'bpSystolic' => 'nullable|integer|between:30,300',
            'bpDiastolic' => 'nullable|integer|between:20,200',
            'pulse' => 'nullable|integer|between:20,250',
            'temperature' => 'nullable|numeric|between:25,45',
            'spo2' => 'nullable|integer|between:40,100',
            'weight' => 'nullable|numeric|between:0.5,400',
            'height' => 'nullable|numeric|between:30,250',
            'note' => 'nullable|string|max:500',
        ], [
            'patientId.required' => 'Pick the patient first.',
        ]);

        $readings = array_filter([
            $this->bpSystolic, $this->bpDiastolic, $this->pulse,
            $this->temperature, $this->spo2, $this->weight, $this->height,
        ], fn ($v) => $v !== '' && $v !== null);

        if (! $readings) {
            $this->addError('pulse', 'Record at least one measurement.');

            return;
        }

        $appt = $this->appointment !== '' ? appointment::find((int) $this->appointment) : null;
        $stayRow = $this->stay !== '' ? stay::find((int) $this->stay) : null;

        // Never file an observation under an encounter that belongs to
        // somebody else after the patient select was switched.
        if ($appt && (int) $appt->patient_id !== (int) $this->patientId) {
            $appt = null;
            $this->appointment = '';
        }

        if ($stayRow && (int) $stayRow->patient_id !== (int) $this->patientId) {
            $stayRow = null;
            $this->stay = '';
        }

        Vital::create([
            'patient_id' => (int) $this->patientId,
            'appointment_id' => $appt?->id,
            'stay_id' => $stayRow?->id,
            'taken_by' => auth()->id(),
            'bp_systolic' => $this->bpSystolic !== '' ? (int) $this->bpSystolic : null,
            'bp_diastolic' => $this->bpDiastolic !== '' ? (int) $this->bpDiastolic : null,
            'pulse' => $this->pulse !== '' ? (int) $this->pulse : null,
            'temperature' => $this->temperature !== '' ? (float) $this->temperature : null,
            'spo2' => $this->spo2 !== '' ? (int) $this->spo2 : null,
            'weight' => $this->weight !== '' ? (float) $this->weight : null,
            'height' => $this->height !== '' ? (float) $this->height : null,
            'note' => $this->note ?: null,
            'taken_at' => now(),
        ]);

        // Check-in vitals mean the patient has arrived: advance the OPD flow.
        if ($appt && in_array($appt->status, ['pending', 'confirmed'], true)) {
            $appt->update(['status' => 'waiting']);
        }

        $this->resetReadings();
        session()->flash('message', 'Vitals recorded.');
    }

    public function render()
    {
        if (! hms_can('vitals')) {
            abort(403);
        }

        $patient = $this->patientId !== '' ? patient::find((int) $this->patientId) : null;

        return view('livewire.admins.vitals', [
            'patient' => $patient,
            'patients' => patient::orderBy('name')->limit(300)->get(['id', 'name']),
            'recent' => $patient
                ? Vital::with('recorder:id,name')
                    ->where('patient_id', $patient->id)
                    ->latest('taken_at')
                    ->limit(10)
                    ->get()
                : collect(),
            'queue' => appointment::with('patient:id,name,age,gender', 'doctor.employ:id,name')
                ->whereDate('intime', today())
                ->whereNotIn('status', ['cancelled', 'terminated'])
                ->orderBy('intime')
                ->limit(30)
                ->get(),
            'wardList' => stay::with('patient:id,name,age,gender', 'room:id,name', 'bed:id,bed_number')
                ->where('status', 'active')
                ->orderBy('start_time')
                ->limit(30)
                ->get(),
        ]);
    }
}
