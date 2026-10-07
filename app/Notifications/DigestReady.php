<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DigestReady extends Notification
{
    use Queueable;

    public function __construct(public string $message)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return ['message' => $this->message];
    }
}