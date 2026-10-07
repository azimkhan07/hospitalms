<?php

namespace App\Http\Livewire\Admins;

use App\Models\CalendarEvent;
use App\Models\Meeting;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('admins.layouts.app')]
class Events extends Component
{
    public ?string $monthCursor = null;

    public ?string $selectedDate = null;

    public string $title = '';

    public string $type = CalendarEvent::BLOOD_CAMP;

    public string $startsOn = '';

    public string $startsAt = '09:00';

    public ?string $endsOn = null;

    public ?string $endsAt = null;

    public string $description = '';

    public ?int $editingId = null;

    public function render()
    {
        if (! hms_can('calendar')) {
            abort(403);
        }

        $month = $this->monthCursor
            ? Carbon::parse($this->monthCursor)->startOfMonth()
            : Carbon::now()->startOfMonth();

        $gridStart = $month->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $events = CalendarEvent::whereBetween('starts_at', [$gridStart, $gridEnd->copy()->endOfDay()])
            ->orderBy('starts_at')
            ->get();

        $meetings = collect();
        if (hms_can('meetings')) {
            $meetings = Meeting::whereBetween('scheduled_at', [$gridStart, $gridEnd->copy()->endOfDay()])
                ->orderBy('scheduled_at')
                ->get();
        }

        $upcoming = CalendarEvent::where('starts_at', '>=', now()->subDay())
            ->latest('starts_at')
            ->limit(8)
            ->get();

        return view('livewire.admins.events', [
            'month' => $month,
            'gridStart' => $gridStart,
            'gridEnd' => $gridEnd,
            'byDate' => $events->groupBy(fn ($e) => $e->starts_at->format('Y-m-d')),
            'meetingsByDate' => $meetings->groupBy(fn ($m) => $m->scheduled_at->format('Y-m-d')),
            'upcoming' => $upcoming,
            'canManage' => hms_can('calendar.manage'),
        ]);
    }

    protected function rules(): array
    {
        return [
            'title' => 'required|max:120',
            'type' => ['required', 'in:'.implode(',', [CalendarEvent::BLOOD_CAMP, CalendarEvent::VISITING, CalendarEvent::GENERAL])],
            'startsOn' => 'required|date',
            'startsAt' => 'required|date_format:H:i',
            'endsOn' => 'nullable|date|after_or_equal:startsOn',
            'description' => 'nullable|max:255',
        ];
    }

    public function selectDate(string $date): void
    {
        $this->selectedDate = $date;
        $this->startsOn = $date;
        $this->monthCursor = Carbon::parse($date)->startOfMonth()->toDateString();
    }

    public function shiftMonth(int $step): void
    {
        $current = $this->monthCursor
            ? Carbon::parse($this->monthCursor)->startOfMonth()
            : Carbon::now()->startOfMonth();

        $this->monthCursor = $current->copy()->addMonthsNoOverflow($step)->toDateString();
    }

    public function gotoToday(): void
    {
        $this->monthCursor = Carbon::now()->startOfMonth()->toDateString();
    }

    public function openEvent(int $id): void
    {
        $event = CalendarEvent::findOrFail($id);

        $this->editingId = $event->id;
        $this->title = $event->title;
        $this->type = $event->type;
        $this->startsOn = $event->starts_at->toDateString();
        $this->startsAt = $event->starts_at->format('H:i');
        $this->endsOn = $event->ends_at?->toDateString();
        $this->endsAt = $event->ends_at?->format('H:i');
        $this->description = (string) $event->description;
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->title = '';
        $this->type = CalendarEvent::BLOOD_CAMP;
        $this->startsOn = $this->selectedDate ?? '';
        $this->startsAt = '09:00';
        $this->endsOn = null;
        $this->endsAt = null;
        $this->description = '';
    }

    public function save(): void
    {
        if (! hms_can('calendar.manage')) {
            abort(403);
        }

        $this->validate();
        $this->selectedDate = $this->startsOn;

        $startsAt = Carbon::parse($this->startsOn)->setTimeFromTimeString($this->startsAt);
        $endsAt = $this->endsOn ? Carbon::parse($this->endsOn)->setTimeFromTimeString($this->endsAt ?: '17:00') : null;

        $data = [
            'title' => $this->title,
            'type' => $this->type,
            'color' => CalendarEvent::colorFor($this->type),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'description' => $this->description ?: null,
        ];

        if ($this->editingId) {
            CalendarEvent::findOrFail($this->editingId)->update($data);
            session()->flash('message', 'Event updated.');
        } else {
            $data['created_by'] = auth()->id();
            CalendarEvent::create($data);
            session()->flash('message', 'Event scheduled.');
        }

        $this->monthCursor = Carbon::parse($startsAt)->startOfMonth()->toDateString();
        $this->resetForm();
    }

    public function removeEvent(int $id): void
    {
        if (! hms_can('calendar.manage')) {
            abort(403);
        }

        CalendarEvent::findOrFail($id)->delete();

        if ($this->editingId === $id) {
            $this->resetForm();
        }

        session()->flash('message', 'Event removed.');
    }
}