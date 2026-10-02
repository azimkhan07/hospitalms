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
            'status' => 'required|in:pending,confirmed,completed,cancelled',
            'notes' => 'nullable|max:500',
        ], [
            'patient.required' => 'Please choose a patient.',
            'doctor.required' => 'Please choose a doctor.',
            'start_timeee.required' => 'Set the appointment start time.',
            'endtime.after_or_equal' => 'End time cannot be before the start time.',
        ]);

        appointment::create([
            'patient_id' => $this->patient,
            'doctor_id' => $this->doctor,
            'intime' => $this->start_timeee,
            'outtime' => $this->endtime ?: null,
            'status' => $this->status,
            'notes' => $this->notes ?: null,
        ]);

        $this->reset(['patient', 'doctor', 'start_timeee', 'endtime', 'notes']);
        $this->status = 'pending';

        session()->flash('message', 'Appointment created successfully.');
    }

    public function edit($id): void
    {
        $appointment = appointment::findOrFail($id);

        $this->edit_appointment_id = $id;
        $this->patient = $appointment->patient_id;
        $this->doctor = $appointment->doctor_id;
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
            'status' => 'required|in:pending,confirmed,completed,cancelled',
            'notes' => 'nullable|max:500',
        ]);

        $appointment = appointment::findOrFail($id);
        $appointment->update([
            'patient_id' => $this->patient,
            'doctor_id' => $this->doctor,
            'intime' => $this->start_timeee,
            'outtime' => $this->endtime ?: null,
            'status' => $this->status,
            'notes' => $this->notes ?: null,
        ]);

        $this->reset(['patient', 'doctor', 'start_timeee', 'endtime', 'notes', 'edit_appointment_id']);
        $this->status = 'pending';
        $this->button_text = 'Add New Appointment';

        session()->flash('message', 'Appointment updated successfully.');
    }

    public function delete($id): void
    {
        appointment::findOrFail($id)->delete();

        session()->flash('message', 'Appointment deleted successfully.');
    }

    public function cancelEdit(): void
    {
        $this->reset(['patient', 'doctor', 'start_timeee', 'endtime', 'notes', 'edit_appointment_id']);
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

        $appointments = appointment::with(['patient:id,name', 'doctor.employ:id,name'])
            ->when($this->search !== '', function ($q) {
                $term = '%'.mb_strtolower($this->search).'%';
                $q->whereHas('patient', fn ($p) => $p->whereRaw('LOWER(name) LIKE ?', [$term]));
            })
            ->orderByDesc('intime')
            ->paginate(15);

        return view('livewire.admins.appiontment', [
            'patients' => patient::orderBy('name')->limit(300)->get(),
            'doctors' => doctor::with('employ:id,name')->get(),
            'appointments' => $appointments,
        ]);
    }
}
