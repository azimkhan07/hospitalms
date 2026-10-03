<?php

namespace App\Http\Livewire\Admins;

use App\Models\LeaveRequest;
use App\Models\Role;
use App\Notifications\LeaveRequestStatus;
use App\Notifications\LeaveRequested;
use Illuminate\Database\Eloquent\Builder;
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

    public function mount(): void
    {
        // A reviewer who cannot apply (the admin) lands straight on the queue.
        if (! $this->canApply && $this->canReview) {
            $this->tab = 'review';
        }
    }

    public function render()
    {
        $canApply = hms_can('leave');
        $canReview = hms_can('leave.review');
        $canDecide = hms_can_decide_leave();

        if (! $canApply && ! $canReview) {
            abort(403);
        }

        return view('livewire.admins.leave-requests', [
            'canApply' => $canApply,
            'canReview' => $canReview,
            'canDecide' => $canDecide,
            'approver' => hms_leave_approver_label(),
            'applicant' => auth()->user(),
            'reviewerNames' => $canReview ? $this->reviewerNames() : '',
            'mine' => $canApply
                ? LeaveRequest::where('user_id', auth()->id())
                    ->orderByDesc('created_at')
                    ->limit(12)
                    ->get()
                : collect(),
            'queue' => $canReview
                ? $this->reviewableQuery()
                    ->with(['user.role:id,name,slug', 'reviewer:id,name'])
                    ->where('status', 'pending')
                    ->where('user_id', '!=', auth()->id())
                    ->orderBy('from_date')
                    ->paginate(10)
                : null,
            'decided' => $canReview
                ? $this->reviewableQuery()
                    ->with(['user:id,name', 'reviewer:id,name'])
                    ->whereIn('status', ['approved', 'rejected'])
                    ->orderByDesc('reviewed_at')
                    ->limit(10)
                    ->get()
                : collect(),
        ]);
    }

    /**
     * May this user apply for their own leave? The admin may not.
     */
    public function getCanApplyProperty(): bool
    {
        return hms_can('leave');
    }

    /**
     * May this user decide on other people's leave?
     */
    public function getCanReviewProperty(): bool
    {
        return hms_can('leave.review');
    }

    /**
     * May this user actually approve/reject, as opposed to only watching the
     * queue? The Dean decides first; the admin only decides when there is no
     * Dean to do it.
     */
    public function getCanDecideProperty(): bool
    {
        return hms_can_decide_leave();
    }

    /**
     * Label for whoever holds the first refusal right now.
     */
    public function getApproverProperty(): string
    {
        return hms_leave_approver_label();
    }

    /**
     * Leave raised by staff of the signed-in tenant.
     *
     * Leave rows carry no tenant_id of their own, so the tenant boundary is
     * applied through the applicant. Without this an admin of one tenant would
     * see and decide on another tenant's requests.
     */
    private function reviewableQuery(): Builder
    {
        return LeaveRequest::whereHas(
            'user',
            fn (Builder $q) => $q->where('tenant_id', auth()->user()->tenant_id)
        );
    }

    /**
     * A request this tenant is allowed to act on, or null.
     */
    private function findReviewable(int $id): ?LeaveRequest
    {
        return $this->reviewableQuery()->find($id);
    }

    /**
     * Role names of the staff who can act on a leave request.
     *
     * Names whoever currently holds the first refusal, so the UI does not
     * advertise an admin who is only standing in for a vacant Dean post.
     */
    private function reviewerNames(): string
    {
        $slugs = hms_leave_approver_slugs();

        return Role::whereIn('slug', $slugs)
            ->pluck('name')
            ->filter()
            ->implode(', ');
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['mine', 'review', 'decided'], true)) {
            $tab = 'mine';
        }

        // Only reviewers may open the review tabs, otherwise the view would
        // render a null queue.
        if (in_array($tab, ['review', 'decided'], true) && ! $this->canReview) {
            $tab = 'mine';
        }

        // The admin cannot apply for leave, so there is no "mine" tab for them.
        if ($tab === 'mine' && ! $this->canApply) {
            $tab = $this->canReview ? 'review' : 'mine';
        }

        $this->tab = $tab;
    }

    public function submitRequest(): void
    {
        // The admin manages the system and does not apply for leave.
        if (! $this->canApply) {
            abort(403);
        }

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
        // Only whoever can actually decide right now gets pinged, so a Dean is
        // not notified about requests the admin is going to clear anyway.
        $reviewerRoleIds = Role::whereIn('slug', hms_leave_approver_slugs())->pluck('id');

        \App\Models\User::whereIn('role_id', $reviewerRoleIds)
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('id', '!=', auth()->id())
            ->where('is_active', true)
            ->get()
            ->each(fn ($u) => $u->notify(new LeaveRequested($leave)));
    }

    public function openReview(int $id): void
    {
        if (! $this->canReview) {
            abort(403);
        }

        $leave = $this->findReviewable($id);

        // Another tenant's request, or nobody reviews their own.
        if (! $leave || $leave->user_id === auth()->id()) {
            abort(403);
        }

        $this->reviewingId = $id;
        $this->reviewNote = '';
    }

    public function decide(string $status): void
    {
        // Watching the queue is allowed for every reviewer; deciding is not.
        if (! $this->canDecide) {
            abort(403);
        }

        if (! in_array($status, ['approved', 'rejected'], true)) {
            abort(422);
        }

        $this->validate([
            'reviewNote' => $status === 'rejected' ? 'required|max:300' : 'nullable|max:300',
        ]);

        $leave = $this->findReviewable($this->reviewingId);

        if (! $leave) {
            abort(403);
        }

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