<?php

namespace App\Http\Livewire\Admins;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('admins.layouts.app')]
class AttendanceRegister extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public string $date = '';

    public string $role = '';

    public string $search = '';

    public function mount(): void
    {
        $this->date = Carbon::today()->toDateString();
    }

    public function updatingDate(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        if (! hms_can('attendance')) {
            abort(403);
        }

        $tenantId = auth()->user()->tenant_id;

        $rows = Attendance::query()
            ->where('tenant_id', $tenantId)
            ->with(['user:id,name,role_id'])
            ->whereDate('work_date', $this->date ?: Carbon::today()->toDateString())
            ->when($this->role !== '', fn (Builder $q) => $q->whereHas(
                'user',
                fn (Builder $u) => $u->whereHas('role', fn (Builder $r) => $r->where('slug', $this->role))
            ))
            ->when($this->search !== '', fn (Builder $q) => $q->whereHas(
                'user',
                fn (Builder $u) => $u->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($this->search).'%'])
            ))
            ->orderBy('check_in_at')
            ->get();

        // Everyone on the tenant roster, so absentees show up too.
        $onDuty = User::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->when($this->role !== '', fn (Builder $q) => $q->whereHas(
                'role',
                fn (Builder $r) => $r->where('slug', $this->role)
            ))
            ->when($this->search !== '', fn (Builder $q) => $q->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($this->search).'%']))
            ->with('role:id,name,slug')
            ->orderBy('name')
            ->get();

        $seen = $rows->pluck('user_id')->all();

        $absent = $onDuty->filter(fn (User $u) => ! in_array($u->id, $seen, true))
            ->map(fn (User $u) => [
                'user' => $u,
                'status' => 'absent',
                'worked_minutes' => null,
            ]);

        $present = $rows->map(fn (Attendance $a) => [
            'attendance' => $a,
            'status' => $a->status(),
            'worked_minutes' => $a->workedMinutes(),
        ]);

        $merged = $present->concat($absent)
            ->sortBy(fn ($r) => $r['user']->name ?? optional($r['attendance']->user)->name)
            ->values();

        return view('livewire.admins.attendance-register', [
            'rows' => $merged,
            'rules' => hms_attendance(),
            'stats' => [
                'onDuty' => $onDuty->count(),
                'present' => $merged->where('status', 'present')->count(),
                'halfDay' => $merged->where('status', 'half_day')->count(),
                'absent' => $merged->where('status', 'absent')->count(),
                'pending' => $merged->where('status', 'pending')->count(),
            ],
            'roles' => $onDuty->pluck('role')->filter()->unique('id')->sortBy('level')->values(),
        ]);
    }
}
