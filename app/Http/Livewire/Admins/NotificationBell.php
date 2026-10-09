<?php

namespace App\Http\Livewire\Admins;

use App\Notifications\AppointmentCalled;
use App\Notifications\DoctorAlertRaised;
use App\Notifications\LeaveRequestStatus;
use App\Notifications\LeaveRequested;
use App\Notifications\MeetingScheduled;
use Livewire\Attributes\Computed;
use Livewire\Component;

class NotificationBell extends Component
{
    public bool $open = false;

    #[Computed]
    public function unreadCount(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    #[Computed]
    public function items()
    {
        return auth()->user()
            ->notifications()
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'icon' => match ($n->type) {
                    MeetingScheduled::class => 'fa-calendar-alt',
                    AppointmentCalled::class => 'fa-bell',
                    DoctorAlertRaised::class => 'fa-exclamation-triangle',
                    LeaveRequested::class, LeaveRequestStatus::class => 'fa-plane-departure',
                    default => 'fa-bell',
                },
                'colour' => match ($n->type) {
                    MeetingScheduled::class => 'text-info',
                    AppointmentCalled::class => 'text-danger',
                    DoctorAlertRaised::class => 'text-danger',
                    LeaveRequested::class => 'text-warning',
                    LeaveRequestStatus::class => $n->data['status'] === 'approved' ? 'text-success' : 'text-danger',
                    default => 'text-muted',
                },
                'text' => $n->data['message'] ?? 'New notification',
                'time' => $n->created_at->diffForHumans(),
                'url' => $this->linkFor($n),
            ]);
    }

    #[Computed]
    public function pendingLeaveCount(): int
    {
        return hms_can('leave.review')
            ? \App\Models\LeaveRequest::where('status', 'pending')->count()
            : 0;
    }

    private function linkFor($notification): ?string
    {
        if ($notification->type === MeetingScheduled::class) {
            return route('admin_meetings');
        }

        // kind 'doctor-alert': the escalation is answered from the doctor desk.
        if ($notification->type === DoctorAlertRaised::class) {
            return route('admin_consultations');
        }

        return route('admin_leave');
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();

        session()->flash('success', 'All notifications marked as read.');
    }

    public function markRead(int $id): void
    {
        $notification = auth()->user()->notifications()->find($id);

        $notification?->markAsRead();

        if ($url = $notification ? $this->linkFor($notification) : null) {
            $this->redirect($url);
        }
    }

    public function render()
    {
        return view('livewire.admins.notification-bell');
    }
}