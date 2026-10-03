<?php

namespace App\Http\Livewire\Admins;

use App\Models\LeaveRequest;
use App\Models\Role;
use App\Notifications\LeaveRequestStatus;
use App\Notifications\LeaveRequested;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('admins.layouts.app')]
class LeaveRequests extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public string $tab = 'mine';

    public string $type = 'casual';

    public string $fromDate = '';

    public string $toDate = '';

    public string $reason = '';

    public ?int $reviewingId = null;

    public string $reviewNote = '';

    public function render()
    {
        if (! hms_can('leave')) {
            abort(403);
        }

        $canReview = hms_can('leave.review');

        return view('livewire.admins.leave-requests', [
            'canReview' => $canReview,
            'applicant' => auth()->user(),
            'reviewerNames' => $canReview ? $this->reviewerNames() : collect(),
            'mine' => LeaveRequest::where('user_id', auth()->id())
                ->orderByDesc('created_at')
                ->limit(12)
                ->get(),
            'queue' => $canReview
                ? LeaveRequest::with(['user.role:id,name,slug', 'reviewer:id,name'])
                    ->where('status', 'pending')
                    ->where('user_id', '!=', auth()->id())
                    ->orderBy('from_date')
                    ->paginate(10)
                : null,
            'decided' => $canReview
                ? LeaveRequest::with(['user:id,name', 'reviewer:id,name'])
                    ->whereIn('status', ['approved', 'rejected'])
                    ->orderByDesc('reviewed_at')
                    ->limit(10)
                    ->get()
                : collect(),
        ]);
    }

    /**
     * Role names of the staff who can act on a leave request.
     */
    private function reviewerNames(): string
    {
        return Role::whereIn('slug', ['admin', 'moderator', 'hr'])
            ->pluck('name')
            ->filter()
            ->implode(', ');
    }

    public function setTab(string $tab): void
    {
        // Only reviewers may open the review tabs, otherwise the view would
        // render a null queue.
        if (in_array($tab, ['review', 'decided'], true) && ! hms_can('leave.review')) {
            $tab = 'mine';
        }

        $this->tab = in_array($tab, ['mine', 'review', 'decided'], true) ? $tab : 'mine';
    }

    public function submitRequest(): void
    {
        $this->validate([
            'type' => 'required|in:casual,sick,annual,maternity,unpaid',
            'fromDate' => 'required|date',
            'toDate' => 'required|date|after_or_equal:fromDate',
            'reason' => 'nullable|max:500',
        ]);

        LeaveRequest::create([
            'user_id' => auth()->id(),
            'type' => $this->type,
            'from_date' => Carbon::parse($this->fromDate)->toDateString(),
            'to_date' => Carbon::parse($this->toDate)->toDateString(),
            'reason' => $this->reason ?: null,
            'status' => 'pending',
        ]);

        $leave = LeaveRequest::where('user_id', auth()->id())
            ->latest('id')
            ->first();

        $this->notifyReviewers($leave);

        $this->reset(['fromDate', 'toDate', 'reason']);
        $this->tab = 'mine';

        session()->flash('success', 'Leave request sent for approval.');
    }

    private function notifyReviewers(LeaveRequest $leave): void
    {
        $reviewerRoleIds = Role::whereIn('slug', ['admin', 'moderator', 'hr'])->pluck('id');

        \App\Models\User::whereIn('role_id', $reviewerRoleIds)
            ->where('id', '!=', auth()->id())
            ->where('is_active', true)
            ->get()
            ->each(fn ($u) => $u->notify(new LeaveRequested($leave)));
    }

    public function openReview(int $id): void
    {
        if (! hms_can('leave.review')) {
            abort(403);
        }

        $leave = LeaveRequest::findOrFail($id);

        // Nobody reviews their own request, not even an admin.
        if ($leave->user_id === auth()->id()) {
            abort(403);
        }

        $this->reviewingId = $id;
        $this->reviewNote = '';
    }

    public function decide(string $status): void
    {
        if (! hms_can('leave.review')) {
            abort(403);
        }

        if (! in_array($status, ['approved', 'rejected'], true)) {
            abort(422);
        }

        $this->validate([
            'reviewNote' => $status === 'rejected' ? 'required|max:300' : 'nullable|max:300',
        ]);

        $leave = LeaveRequest::findOrFail($this->reviewingId);

        if ($leave->user_id === auth()->id() || $leave->status !== 'pending') {
            session()->flash('error', 'That request is no longer awaiting your decision.');

            $this->reviewingId = null;
            $this->reviewNote = '';

            return;
        }

        $leave->update([
            'status' => $status,
            'review_note' => $this->reviewNote ?: null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => Carbon::now(),
        ]);

        $leave->user?->notify(new LeaveRequestStatus($leave->id, $status, $this->reviewNote));

        $this->reviewingId = null;
        $this->reviewNote = '';

        session()->flash('success', 'Leave request '.$status.'.');
    }

    public function updatedTab(): void
    {
        $this->resetPage();
    }
}