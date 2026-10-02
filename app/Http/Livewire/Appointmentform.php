<?php

namespace App\Http\Livewire;

use App\Models\doctor;
use App\Models\requestedAppointment;
use Livewire\Component;

class Appointmentform extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public $doctor_id = '';

    public $stime = '';

    public string $address = '';

    public string $message = '';

    public bool $booked = false;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'email' => 'nullable|email|max:150',
            'phone' => 'required|string|max:13',
            'doctor_id' => 'required|exists:doctors,id',
            'stime' => 'required|date|after:now',
            'address' => 'required|string|max:150',
            'message' => 'nullable|string|max:550',
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'Please enter your name.',
            'phone.required' => 'A phone number is required so we can confirm.',
            'doctor_id.required' => 'Please choose a doctor.',
            'doctor_id.exists' => 'The selected doctor is not available.',
            'stime.required' => 'Please pick a preferred date and time.',
            'stime.after' => 'Please choose a time in the future.',
            'address.required' => 'Please enter your address.',
        ];
    }

    public function store_requested_appointment(): void
    {
        $this->validate();

        requestedAppointment::create([
            'name' => $this->name,
            'email' => $this->email ?: null,
            'phone' => $this->phone,
            'doctor_id' => $this->doctor_id,
            'stime' => $this->stime,
            'address' => $this->address,
            'message' => $this->message ?: 'Appointment request from website.',
        ]);

        $this->reset(['name', 'email', 'phone', 'doctor_id', 'stime', 'address', 'message']);
        $this->booked = true;

        session()->flash('booked', 'Your appointment request has been received. Our team will call you to confirm.');
    }

    public function render()
    {
        return view('livewire.appointmentform', [
            'doctors' => doctor::with('employ:id,name')
                ->get()
                ->sortBy(fn ($d) => $d->employ?->name),
        ]);
    }
}
