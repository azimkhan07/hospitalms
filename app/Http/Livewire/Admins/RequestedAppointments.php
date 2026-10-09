<?php

namespace App\Http\Livewire\Admins;

use App\Models\appointment;
use App\Models\doctor;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use App\Models\requestedAppointment;
use App\Models\patient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\WithPagination;

#[Layout('admins.layouts.app')]
class RequestedAppointments extends Component
{
    use WithPagination;


    public $name;
    public $email;
    public $phone;
    public $doctor;
    public $message;
    public $address;
    public $stime;
    public $_page;
    public $edit_appointment_id;

    #[Url]
    public string $statusFilter = 'pending';
    public function mount()
    {
        $this->_page = 'index';
    }

    public function edit($id)
    {
        $appointment = requestedAppointment::findOrFail($id);
        $this->edit_appointment_id = $id;

        $this->patient = $appointment->patient_id;
        $this->doctor = $appointment->doctor_id;
        $this->start_time = $appointment->intime;
        $this->end_time = $appointment->outtime;
        $this->_page = "edit";
    }

    public function add_appointment()
    {
        if ($this->edit_appointment_id) {

            $this->update($this->edit_appointment_id);

        } else {
            $this->validate([
                "name" => "required",
                "email" => "required",
                "phone" => "required",
                "doctor" => "required",
                "message" => "required",
                "address" => "required",
                "stime" => "required",
            ]);
            requestedAppointment::create([
                "name" => $this->name,
                "email" => $this->email,
                "phone" => $this->phone,
                "doctor_id" => $this->doctor,
                "message" => $this->message,
                "address" => $this->address,
                "stime" => $this->stime,

            ]);
            //unset variables
            $this->name;
            $this->email;
            $this->phone;
            $this->doctor;
            $this->message;
            $this->address;
            $this->stime;
            $this->_page = "index";

            session()->flash('message', 'Appointment Created successfully.');
        }

    }
    public function update($edit_appointment_id)
    {
        $this->validate([
            'patient' => 'required|numeric',
            'doctor' => 'required|numeric',
        ]);

        $appointment = requestedAppointment::findOrFail($edit_appointment_id);
        $appointment->patient_id = $this->patient;
        $appointment->doctor_id = $this->doctor;
        $appointment->intime = $this->start_time;
        $appointment->outtime = $this->end_time;
        $appointment->save();

        //unset variables
        $this->patient = "";
        $this->doctor = "";
        $this->start_time = "";
        $this->end_time = "";
        $this->_page = "index";
        session()->flash('message', 'Appointment Updated successfully.');
    }


    public function show_create_form()
    {
        $this->_page = "create";
    }

    protected $paginationTheme = 'bootstrap';

    public function add_patient($id)
    {
        $request = requestedAppointment::find($id);
        patient::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
        ]);
        session()->flash('message', 'Patient Added Successfully.');
    }

    /**
     * Accept a website request: register the patient (or match them by
     * phone/email) and put a real appointment on the doctor's board.
     */
    public function approve(int $id)
    {
        $request = requestedAppointment::findOrFail($id);

        if ($request->status === 'approved' && $request->appointment_id) {
            session()->flash('error', 'This request was already approved.');

            return;
        }

        $patientRow = patient::where('phone', $request->phone)->first();

        if (! $patientRow && $request->email) {
            $patientRow = patient::where('email', $request->email)->first();
        }

        $patientRow ??= patient::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
        ]);

        $intime = now();
        if ($request->stime) {
            try {
                $intime = is_numeric($request->stime)
                    ? Carbon::createFromTimestamp((int) $request->stime)
                    : Carbon::parse($request->stime);
            } catch (\Throwable) {
                $intime = now();
            }
        }

        $appt = appointment::create([
            'patient_id' => $patientRow->id,
            'doctor_id' => $request->doctor_id,
            'intime' => $intime,
            'status' => 'confirmed',
            'notes' => $request->message ? Str::limit($request->message, 500) : null,
        ]);

        $request->update([
            'status' => 'approved',
            'patient_id' => $patientRow->id,
            'appointment_id' => $appt->id,
        ]);

        session()->flash('message', 'Request accepted - appointment #'.$appt->id.' confirmed.');
    }

    public function cancelRequest(int $id)
    {
        $request = requestedAppointment::findOrFail($id);
        $request->update(['status' => 'cancelled']);
        session()->flash('message', 'Request declined.');
    }

    public function delete($id)
    {
        $patient = requestedAppointment::find($id)->delete();
        session()->flash('message', 'Appointment Deleted Successfully.');
    }
    public function render()
    {
        if (! hms_can('appointments')) { abort(403); }

        if ($this->_page == "index") {
            return view('livewire.admins.requested-appointments.index', [
                'appointments' => requestedAppointment::with('appointment:id,status')
                    ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
                    ->latest()
                    ->paginate(10),
                'counts' => [
                    'pending' => requestedAppointment::where('status', 'pending')->count(),
                    'approved' => requestedAppointment::where('status', 'approved')->count(),
                    'cancelled' => requestedAppointment::where('status', 'cancelled')->count(),
                ],
            ]);
        } elseif ($this->_page == "create") {
            return view('livewire.admins.requested-appointments.create', [
                'patients' => patient::all(),
                'doctors' => doctor::all(),
            ]);
        } elseif ($this->_page == "edit") {
            return view('livewire.admins.requested-appointments.edit', [
                'appointment' => requestedAppointment::findOrFail($this->edit_operation_report_id),
                'doctors' => doctor::all(),
                'patients' => patient::all(),
            ]);
        }
    }
}
