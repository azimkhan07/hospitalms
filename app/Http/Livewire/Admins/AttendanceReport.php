<?php

namespace App\Http\Livewire\Admins;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('admins.layouts.app')]
class AttendanceReport extends Component
{
    public string $month = '';

    public string $role = '';

    public function mount(): void
    {
        $this->month = Carbon::today()->format('Y-m');
    }

    public function render()
    {
        if (! hms_can('attendance')) {
            abort(403);
        }

        $tenantId = auth()->user()->tenant_id;
        $month = $this->month ?: Carbon::today()->format('Y-m');
        $start = Carbon::parse($month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $staff = User::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->when($this->role !== '', fn (Builder $q) => $q->whereHas(
                'role',
                fn (Builder $r) => $r->where('slug', $this->role)
            ))
            ->with('role:id,name,slug')
            ->orderBy('name')
            ->get();

        // One query for the whole month, grouped in memory: a per-cell query
        // here would mean staff x days database round trips.
        $rows = Attendance::where('tenant_id', $tenantId)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('check_in_at')
            ->get()
            ->groupBy(fn (Attendance $a) => $a->user_id.'|'.$a->work_date->toDateString());

        $rules = hms_attendance();

        $report = $staff->map(function (User $u) use ($rows, $rules, $start) {
            $days = [];
            $present = 0;
            $half = 0;
            $absent = 0;
            $totalMinutes = 0;

            for ($d = $start->copy(); $d->month === $start->month; $d->addDay()) {
                $key = $u->id.'|'.$d->toDateString();
                $day = $rows->get($key);
                $worked = $day?->sum(fn (Attendance $a) => $a->workedMinutes() ?? 0);

                if (! $day || ! $worked) {
                    // No sign-in at all: a scheduled weekly off, or a real miss.
                    $status = in_array((int) $d->dayOfWeek, $rules['weekend_days'], true)
                        ? 'weekend'
                        : 'absent';
                    $absent += $status === 'absent' ? 1 : 0;

                    $days[$d->toDateString()] = [
                        'status' => $status,
                        'worked' => null,
                        'in' => null,
                        'out' => null,
                    ];
                    continue;
                }

                $status = hms_attendance_status($worked);
                $totalMinutes += $worked;
                $present += $status === 'present' ? 1 : 0;
                $half += $status === 'half_day' ? 1 : 0;
                $absent += $status === 'absent' ? 1 : 0;

                $days[$d->toDateString()] = [
                    'status' => $status,
                    'worked' => $worked,
                    'in' => $day->min('check_in_at'),
                    'out' => $day->max('check_out_at'),
                    'distance' => $day->whereNotNull('check_in_distance_meters')->max('check_in_distance_meters'),
                ];
            }

            return [
                'user' => $u,
                'days' => $days,
                'present' => $present,
                'half_day' => $half,
                'absent' => $absent,
                'hours' => round($totalMinutes / 60, 1),
            ];
        });

        return view('livewire.admins.attendance-report', [
            'report' => $report,
            // Named differently from the $month property on purpose: Livewire
            // merges public properties into the view, and $month is a "Y-m"
            // string that the month picker binds to.
            'monthStart' => $start,
            'rules' => $rules,
            'summary' => [
                'staff' => $staff->count(),
                'present' => $report->sum('present'),
                'halfDay' => $report->sum('half_day'),
                'absent' => $report->sum('absent'),
                'hours' => round($report->sum('hours'), 1),
            ],
            'roles' => \App\Models\Role::whereHas('users', fn (Builder $q) => $q->where('tenant_id', $tenantId))->orderBy('level')->get(),
        ]);
    }
}
