<?php

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class MeetingScheduled extends Notification
{
    use Queueable;

    public function __construct(public Meeting $meeting)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'meeting_id' => $this->meeting->id,
            'title' => $this->meeting->title,
            'scheduled_at' => $this->meeting->scheduled_at->toDateTimeString(),
            'location' => $this->meeting->location,
            'message' => 'A meeting has been scheduled by '
                .optional($this->meeting->creator)->name
                .' on '.$this->meeting->scheduled_at->format('d M Y, h:i A'),
        ];
    }
}