<?php

namespace App\Notifications;

use App\Models\appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A doctor called the next patient: reception sees a pulsing highlight on the
 * queue board telling them to send the patient in.
 */
class AppointmentCalled extends Notification
{
    use Queueable;

    public function __construct(public appointment $appointment)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $token = $this->appointment->token ? '#'.$this->appointment->token : '';

        return [
            'kind' => 'appointment',
            'appointment_id' => $this->appointment->id,
            'token' => $this->appointment->token,
            'patient' => optional($this->appointment->patient)->name,
            'doctor' => optional($this->appointment->doctor?->employ)->name,
            'message' => 'Calling'.$token.' — '.optional($this->appointment->patient)->name
                .' → '.optional($this->appointment->doctor?->employ)->name
                .'. Send the patient in.',
        ];
    }
}