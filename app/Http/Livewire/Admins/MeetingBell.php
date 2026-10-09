<?php

namespace App\Http\Livewire\Admins;

use App\Models\Meeting;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The video-meeting icon in the top navbar (next to the bell). Clicking it
 * drops down the schedule; a meeting's Join button only lights up while the
 * call is joinable, and the organiser gets a Start button (PLAN.md 18g).
 */
class MeetingBell extends Component
{
    public bool $open = false;

    #[Computed]
    public function meetings()
    {
        return Meeting::with(['host:id,name', 'creator:id,name'])
            ->visibleTo(auth()->user())
            ->whereIn('status', ['scheduled', 'live'])
            ->where('scheduled_at', '>=', now()->subHours(3))
            ->orderBy('scheduled_at')
            ->limit(12)
            ->get();
    }

    #[Computed]
    public function liveCount(): int
    {
        return $this->meetings->filter(fn (Meeting $m) => $m->canJoin(auth()->user()))->count();
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function startNow(int $id): void
    {
        $meeting = Meeting::visibleTo(auth()->user())->findOrFail($id);

        abort_unless($meeting->isHost(auth()->user()), 403, 'Only the organiser can start this meeting.');

        $meeting->start(auth()->user());

        $this->redirect($meeting->roomPath());
    }

    public function render()
    {
        return view('livewire.admins.meeting-bell');
    }
}
