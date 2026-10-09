<?php

namespace App\Notifications;

use App\Models\appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * The reminder sweep: reception's bell tells staff a confirmed slot is
 * starting soon so the desk can prep the file and call the patient over.
 */
class AppointmentReminder extends Notification
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
        $doctor = optional($this->appointment->doctor?->employ)->name ?? 'your doctor';
        $intime = $this->appointment->intime;
        $when = $intime->isToday() ? 'today' : 'on '.$intime->format('D j M');

        return [
            'kind' => 'appointment-reminder',
            'title' => 'Appointment reminder',
            'message' => 'Upcoming appointment with '.$doctor.' at '.$intime->format('H:i').' '.$when.'.',
            'url' => route('appointment'),
            'icon' => 'fa-bell',
        ];
    }
}
