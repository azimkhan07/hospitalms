<?php

namespace App\Http\Livewire\Admins;

use App\Models\employee;
use App\Models\LeaveRequest;
use App\Models\Meeting;
use App\Models\medicine;
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

        return view('livewire.admins.dashboard', [
            'employees' => employee::count(),
            'appointments' => \App\Models\appointment::count(),
            'birthreports' => \App\Models\birthreport::count(),
            'operationreports' => \App\Models\operationreport::count(),
            'patients' => \App\Models\patient::count(),
            'hods' => \App\Models\hod::count(),
            'blocks' => \App\Models\block::count(),
            'departments' => \App\Models\department::count(),
            'rooms' => \App\Models\rooms::count(),
            'beds' => \App\Models\beds::count(),
            'subscribers' => \App\Models\subscriber::count(),
            'requestedAppointment' => \App\Models\requestedAppointment::count(),
            'staffAccounts' => \App\Models\User::where('is_active', true)->count(),
            'pendingLeave' => LeaveRequest::where('status', 'pending')->count(),
            'myApprovedLeave' => LeaveRequest::where('user_id', auth()->id())->where('status', 'approved')->count(),
            'upcomingMeetings' => Meeting::upcoming()->count(),
            'myMeetings' => Meeting::upcoming()
                ->where(function ($q) {
                    $q->where('created_by', auth()->id())
                        ->orWhereHas('participants', fn ($p) => $p->where('user_id', auth()->id()));
                })
                ->count(),
            'expiredMedicines' => medicine::whereNull('deleted_at')
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<=', today())
                ->count(),
        ]);
    }
}