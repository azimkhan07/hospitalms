<?php

namespace App\Notifications;

use App\Models\DoctorAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A nurse escalated a patient from the ward: the doctor's bell shows a red
 * triangle with the alert text and opens the consult desk.
 */
class DoctorAlertRaised extends Notification
{
    use Queueable;

    public function __construct(public DoctorAlert $alert)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'doctor-alert',
            'alert_id' => $this->alert->id,
            'title' => 'Doctor alert — '.($this->alert->patient?->name ?? 'Ward'),
            'message' => substr($this->alert->categoriesLabel().': '.$this->alert->message, 0, 140),
            'url' => route('admin_consultations'),
            'icon' => 'fa-exclamation-triangle',
        ];
    }
}
