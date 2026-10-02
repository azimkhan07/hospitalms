<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestStatus extends Notification
{
    use Queueable;

    public function __construct(
        public int $leaveId,
        public string $status,
        public ?string $note = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $word = $this->status === 'approved' ? 'approved' : 'rejected';

        return [
            'leave_id' => $this->leaveId,
            'status' => $this->status,
            'message' => 'Your leave request has been '.$word
                .($this->note ? ': '.$this->note : '.'),
        ];
    }
}