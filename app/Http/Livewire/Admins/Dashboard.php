<?php

namespace App\Http\Livewire\Admins;

use App\Models\LeaveRequest;
use App\Models\Meeting;
use App\Services\DashboardKpis;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('admins.layouts.app')]
class Dashboard extends Component
{
    public function render()
    {
        if (! hms_can('dashboard')) {
            abort(403);
        }

        $myMeetings = Meeting::upcoming()
            ->where(function ($q) {
                $q->where('created_by', auth()->id())
                    ->orWhereHas('participants', fn ($p) => $p->where('user_id', auth()->id()));
            })
            ->count();

        return view('livewire.admins.dashboard', [
            'cards' => DashboardKpis::cards(),
            'pendingLeave' => LeaveRequest::where('status', 'pending')->count(),
            'myApprovedLeave' => LeaveRequest::where('user_id', auth()->id())->where('status', 'approved')->count(),
            'myMeetings' => $myMeetings,
        ]);
    }
}