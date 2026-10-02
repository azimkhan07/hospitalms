<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequested extends Notification
{
    use Queueable;

    public function __construct(public LeaveRequest $leave)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $applicant = optional($this->leave->user)->name ?? 'A staff member';

        return [
            'leave_id' => $this->leave->id,
            'status' => 'pending',
            'message' => $applicant.' has applied for '
                .ucfirst($this->leave->type).' leave from '
                .$this->leave->from_date->format('d M Y').' to '
                .$this->leave->to_date->format('d M Y').'.',
        ];
    }
}