<?php

namespace App\Http\Livewire\Admins;

use App\Models\doctor;
use App\Models\DutyRoster as DutyRosterModel;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Weekly duty roster board: every doctor (or department) on one shift per day.
 * The grid is doctors down the side, the seven days of the week across, with
 * an optional doctor filter and prev/next week navigation.
 */
#[Layout('admins.layouts.app')]
class DutyRoster extends Component
{
    public string $weekStart = '';

    public string $doctorFilter = '';

    public ?int $doctorId = null;

    public string $department = '';

    public string $shift = 'morning';

    public string $dutyDate = '';

    public string $startTime = '';

    public string $endTime = '';

    public string $note = '';

    public function mount(): void
    {
        if (! hms_can('attendance')) {
            abort(403);
        }

        $this->weekStart = $this->monday()->toDateString();
        $this->dutyDate = now()->toDateString();
    }

    public function previousWeek(): void
    {
        $this->weekStart = $this->monday()->subWeek()->toDateString();
    }

    public function nextWeek(): void
    {
        $this->weekStart = $this->monday()->addWeek()->toDateString();
    }

    public function currentWeek(): void
    {
        $this->weekStart = now()->startOfWeek(Carbon::MONDAY)->toDateString();
    }

    /** Assign or overwrite the shift for a doctor (or department) on a date. */
    public function save(): void
    {
        $this->validate([
            'doctorId' => 'nullable|integer',
            'department' => 'nullable|string|max:100',
            'shift' => 'required|in:morning,evening,night,off',
            'dutyDate' => 'required|date',
            'startTime' => 'nullable|date_format:H:i',
            'endTime' => 'nullable|date_format:H:i',
            'note' => 'nullable|string|max:255',
        ]);

        $doctor = $this->doctorId ? doctor::find($this->doctorId) : null;

        if (! $doctor && blank($this->department)) {
            $this->addError('doctorId', 'Choose a doctor or name a department.');

            return;
        }

        $attributes = [
            'tenant_id' => auth()->user()->tenant_id,
            'doctor_id' => $doctor?->id,
            'department' => $doctor ? null : $this->department,
            'shift' => $this->shift,
            'duty_date' => $this->dutyDate,
            'start_time' => $this->startTime ?: null,
            'end_time' => $this->endTime ?: null,
            'note' => $this->note ?: null,
            'created_by' => auth()->id(),
        ];

        $existing = DutyRosterModel::query()
            ->where('duty_date', $this->dutyDate)
            ->when(
                $doctor,
                fn ($q) => $q->where('doctor_id', $doctor->id),
                fn ($q) => $q->whereNull('doctor_id')->where('department', $this->department)
            )
            ->first();

        if ($existing) {
            $existing->update($attributes);
            session()->flash('message', 'Shift updated.');
        } else {
            DutyRosterModel::create($attributes);
            session()->flash('message', 'Shift assigned.');
        }

        $this->reset(['note', 'startTime', 'endTime']);
    }

    public function remove(int $id): void
    {
        $row = DutyRosterModel::find($id);

        if ($row) {
            $row->delete();
            session()->flash('message', 'Shift removed.');
        }
    }

    public function render()
    {
        $start = $this->monday();
        $from = $start->toDateString();
        $to = $start->copy()->addDays(6)->toDateString();

        $days = collect(range(0, 6))->map(fn (int $i) => $start->copy()->addDays($i));

        $doctors = doctor::with('employ:id,name')->orderBy('id')->get();

        if ($this->doctorFilter !== '') {
            $doctors = $doctors->where('id', (int) $this->doctorFilter)->values();
        }

        $entries = [];
        $departmentEntries = [];

        foreach (DutyRosterModel::whereBetween('duty_date', [$from, $to])->get() as $row) {
            $key = $row->duty_date->format('Y-m-d');

            if ($row->doctor_id) {
                $entries[$row->doctor_id][$key] = $row;
            } else {
                $departmentEntries[$row->department ?: 'General'][$key] = $row;
            }
        }

        return view('livewire.admins.duty-roster', [
            'days' => $days,
            'weekEnd' => $start->copy()->addDays(6),
            'doctors' => $doctors,
            'doctorOptions' => doctor::with('employ:id,name')->orderBy('id')->get(),
            'entries' => $entries,
            'departmentEntries' => collect($departmentEntries),
        ]);
    }

    /** The Monday at the head of the week currently on screen. */
    private function monday(): Carbon
    {
        return ($this->weekStart ? Carbon::parse($this->weekStart) : now())
            ->startOfWeek(Carbon::MONDAY);
    }
}
