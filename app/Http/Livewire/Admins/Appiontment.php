<?php

namespace App\Http\Livewire\Admins;

use App\Models\appointment;
use App\Models\doctor;
use App\Models\patient;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('admins.layouts.app')]
class Appiontment extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public string $search = '';

    public $patient = '';

    public $doctor = '';

    public $start_timeee = '';

    public $endtime = '';

    public string $angioMachineId = '';

    public string $schemeId = '';

    public string $status = 'pending';

    public string $notes = '';

    public $edit_appointment_id = null;

    public string $button_text = 'Add New Appointment';

    public function add_appointment(): void
    {
        if ($this->edit_appointment_id) {
            $this->update($this->edit_appointment_id);

            return;
        }

        $this->validate([
            'patient' => 'required|exists:patients,id',
            'doctor' => 'required|exists:doctors,id',
            'start_timeee' => 'required|date',
            'endtime' => 'nullable|date|after_or_equal:start_timeee',
            'status' => 'required|in:'.implode(',', appointment::STATUSES),
            'notes' => 'nullable|max:500',
            'angioMachineId' => 'nullable|exists:angio_machines,id',
            'schemeId' => 'nullable|exists:schemes,id',
        ], [
            'patient.required' => 'Please choose a patient.',
            'doctor.required' => 'Please choose a doctor.',
            'start_timeee.required' => 'Set the appointment start time.',
            'endtime.after_or_equal' => 'End time cannot be before the start time.',
        ]);

        appointment::create([
            'patient_id' => $this->patient,
            'doctor_id' => $this->doctor,
            'angio_machine_id' => $this->angioMachineId ?: null,
            'scheme_id' => $this->schemeId ?: null,
            'intime' => $this->start_timeee,
            'outtime' => $this->endtime ?: null,
            'status' => $this->status,
            'notes' => $this->notes ?: null,
        ]);

        $this->reset(['patient', 'doctor', 'start_timeee', 'endtime', 'notes', 'angioMachineId', 'schemeId']);
        $this->status = 'pending';

        session()->flash('message', 'Appointment created successfully.');
    }

    public function edit($id): void
    {
        $appointment = $this->scoped()->findOrFail($id);

        $this->edit_appointment_id = $id;
        $this->patient = $appointment->patient_id;
        $this->doctor = $appointment->doctor_id;
        $this->angioMachineId = (string) $appointment->angio_machine_id;
        $this->schemeId = (string) $appointment->scheme_id;
        $this->start_timeee = optional($appointment->intime)->format('Y-m-d\TH:i');
        $this->endtime = optional($appointment->outtime)->format('Y-m-d\TH:i');
        $this->status = $appointment->status ?? 'pending';
        $this->notes = (string) $appointment->notes;

        $this->button_text = 'Update Appointment';
    }

    public function update($id): void
    {
        $this->validate([
            'patient' => 'required|exists:patients,id',
            'doctor' => 'required|exists:doctors,id',
            'start_timeee' => 'required|date',
            'endtime' => 'nullable|date|after_or_equal:start_timeee',
            'status' => 'required|in:'.implode(',', appointment::STATUSES),
            'notes' => 'nullable|max:500',
            'angioMachineId' => 'nullable|exists:angio_machines,id',
            'schemeId' => 'nullable|exists:schemes,id',
        ]);

        $appointment = $this->scoped()->findOrFail($id);
        $appointment->update([
            'patient_id' => $this->patient,
            'doctor_id' => $this->doctor,
            'angio_machine_id' => $this->angioMachineId ?: null,
            'scheme_id' => $this->schemeId ?: null,
            'intime' => $this->start_timeee,
            'outtime' => $this->endtime ?: null,
            'status' => $this->status,
            'notes' => $this->notes ?: null,
        ]);

        $this->reset(['patient', 'doctor', 'start_timeee', 'endtime', 'notes', 'angioMachineId', 'schemeId', 'edit_appointment_id']);
        $this->status = 'pending';
        $this->button_text = 'Add New Appointment';

        session()->flash('message', 'Appointment updated successfully.');
    }

    public function delete($id): void
    {
        $this->scoped()->findOrFail($id)->delete();

        session()->flash('message', 'Appointment deleted successfully.');
    }

    /** Reception check-in: the patient has arrived and is waiting. */
    public function markWaiting(int $id): void
    {
        $appointment = $this->scoped()->findOrFail($id);

        if (! in_array($appointment->status, ['pending', 'confirmed'], true)) {
            session()->flash('error', 'Only a pending or confirmed appointment can arrive.');

            return;
        }

        $appointment->update([
            'status' => 'waiting',
            'token' => $appointment->token ?? appointment::whereDate('intime', today())
                ->whereNull('deleted_at')
                ->max('token') + 1,
        ]);

        session()->flash('message', 'Patient marked as waiting. Token #'.$appointment->refresh()->token.'.');
    }

    /** The doctor called; reception sends the patient in and the consult starts. */
    public function sendIn(int $id): void
    {
        $appointment = $this->scoped()->findOrFail($id);

        if ($appointment->status !== 'called') {
            session()->flash('error', 'Only a called patient can be sent in.');

            return;
        }

        $appointment->update(['status' => 'in_consult']);
        session()->flash('message', 'Patient sent in to the doctor.');
    }

    public function goVitals(int $id): void
    {
        $this->redirect(route('admin_vitals', ['appointment' => $id]));
    }

    /**
     * A doctor logging in only ever touches their own rows; everyone else
     * (reception, admin) sees the whole board.
     */
    private function scoped()
    {
        $query = appointment::query();

        if (auth()->user()?->hasRole('doctor')) {
            $query->ownedBy((int) auth()->id());
        }

        return $query;
    }

    public function cancelEdit(): void
    {
        $this->reset(['patient', 'doctor', 'start_timeee', 'endtime', 'notes', 'angioMachineId', 'schemeId', 'edit_appointment_id']);
        $this->status = 'pending';
        $this->button_text = 'Add New Appointment';
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        if (! hms_can('appointments')) {
            abort(403);
        }

        $appointments = $this->scoped()
            ->with(['patient:id,name', 'doctor.employ:id,name', 'angioMachine:id,name', 'scheme:id,name'])
            ->when($this->search !== '', function ($q) {
                $term = '%'.mb_strtolower($this->search).'%';
                $q->whereHas('patient', fn ($p) => $p->whereRaw('LOWER(name) LIKE ?', [$term]));
            })
            ->orderByDesc('intime')
            ->paginate(15);

        $isDoctor = auth()->user()?->hasRole('doctor');

        $waitingQueue = appointment::with(['patient:id,name', 'doctor.employ:id,name'])
            ->whereDate('intime', today())
            ->whereIn('status', ['waiting', 'called', 'in_consult'])
            ->orderBy('token')
            ->get();

        return view('livewire.admins.appiontment', [
            'patients' => patient::orderBy('name')->limit(300)->get(),
            'doctors' => doctor::with('employ:id,name')->orderByDesc('on_duty')->orderBy('id')->get(),
            'angioMachines' => \App\Models\AngioMachine::orderBy('name')->get(['id', 'name']),
            'schemes' => \App\Models\Scheme::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'appointments' => $appointments,
            'waitingQueue' => $waitingQueue,
            'statusFlow' => appointment::STATUSES,
            'showCreateForm' => ! $isDoctor,
        ]);
    }
}
