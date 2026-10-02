<?php

namespace App\Http\Livewire\Admins;

use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\User;
use App\Notifications\MeetingScheduled;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('admins.layouts.app')]
class MeetingCalendar extends Component
{
    public ?string $selectedDate = null;

    public ?string $monthCursor = null;

    public string $title = '';

    public string $agenda = '';

    public string $location = 'Dean Office';

    public string $duration = '30';

    public string $time = '10:00';

    public array $participantIds = [];

    public string $participantSearch = '';

    public bool $showPicker = false;

    public ?int $meetingId = null;

    public ?string $meetingTitle = null;

    public ?string $meetingWhen = null;

    public ?string $meetingLocation = null;

    public ?int $meetingDuration = null;

    public ?string $meetingStatus = null;

    public ?string $meetingAgenda = null;

    public ?string $meetingMyResponse = null;

    public bool $meetingIsOrganiser = false;

    public array $meetingParticipants = [];

    public function render()
    {
        if (! hms_can('meetings')) {
            abort(403);
        }

        $month = $this->monthCursor
            ? Carbon::parse($this->monthCursor)->startOfMonth()
            : Carbon::now()->startOfMonth();

        $gridStart = $month->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $canManage = hms_can('meetings.manage');

        $query = Meeting::with(['users:id,name', 'creator:id,name']);

        // A plain participant only ever sees meetings they created or were invited to.
        if (! $canManage) {
            $query->where(function ($q) {
                $q->where('created_by', auth()->id())
                    ->orWhereHas('participants', fn ($p) => $p->where('user_id', auth()->id()));
            });
        }

        $meetings = $query
            ->whereBetween('scheduled_at', [$gridStart, $gridEnd->copy()->endOfDay()])
            ->orderBy('scheduled_at')
            ->get();

        $mineQuery = Meeting::upcoming()->with(['users:id,name']);

        if (! $canManage) {
            $mineQuery->where(function ($q) {
                $q->where('created_by', auth()->id())
                    ->orWhereHas('participants', fn ($p) => $p->where('user_id', auth()->id()));
            });
        }

        return view('livewire.admins.meeting-calendar', [
            'month' => $month,
            'gridStart' => $gridStart,
            'gridEnd' => $gridEnd,
            'byDate' => $meetings->groupBy(fn ($m) => $m->scheduled_at->format('Y-m-d')),
            'mine' => $mineQuery->limit(8)->get(),
            'users' => $canManage ? $this->staffList() : collect(),
            'canManage' => $canManage,
        ]);
    }

    private function staffList()
    {
        $users = User::with('role:id,name,slug')->orderBy('name')->get();

        $term = trim($this->participantSearch);

        if ($term !== '') {
            $users = $users->filter(
                fn ($u) => str_contains(mb_strtolower($u->name), mb_strtolower($term))
                    || str_contains(mb_strtolower((string) $u->email), mb_strtolower($term))
            );
        }

        return $users->take(40);
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

    public function togglePicker(): void
    {
        if (! hms_can('meetings.manage')) {
            abort(403);
        }

        $this->showPicker = ! $this->showPicker;
    }

    public function selectDate(string $date): void
    {
        $this->selectedDate = $date;
        $this->monthCursor = Carbon::parse($date)->startOfMonth()->toDateString();
        $this->showPicker = false;
    }

    public function saveMeeting(): void
    {
        if (! hms_can('meetings.manage')) {
            abort(403);
        }

        $this->validate([
            'selectedDate' => 'required|date',
            'title' => 'required|max:120',
            'location' => 'required|max:120',
            'time' => 'required|date_format:H:i',
            'duration' => 'required|in:15,30,45,60,90,120',
            'participantIds' => 'required|array|min:1',
            'participantIds.*' => 'exists:users,id',
        ]);

        $meeting = DB::transaction(function () {
            $meeting = Meeting::create([
                'title' => $this->title,
                'agenda' => $this->agenda ?: null,
                'location' => $this->location,
                'scheduled_at' => Carbon::parse($this->selectedDate)->setTimeFromTimeString($this->time),
                'duration_minutes' => (int) $this->duration,
                'created_by' => auth()->id(),
            ]);

            $meeting->users()->syncWithoutDetaching($this->participantIds);

            return $meeting;
        });

        $meeting->load('users:id,name')
            ->users
            ->where('id', '!=', auth()->id())
            ->each(fn ($u) => $u->notify(new MeetingScheduled($meeting)));

        $this->monthCursor = Carbon::parse($this->selectedDate)->startOfMonth()->toDateString();
        $this->reset(['title', 'agenda', 'duration', 'time', 'participantIds', 'participantSearch']);
        $this->time = '10:00';
        $this->location = 'Dean Office';

        $this->dispatch('meeting-created', id: $meeting->id);

        session()->flash('success', 'Meeting scheduled and invitations sent.');
    }

    public function toggleParticipant(int $id): void
    {
        if (! hms_can('meetings.manage')) {
            abort(403);
        }

        $this->participantIds = in_array($id, $this->participantIds, true)
            ? array_values(array_diff($this->participantIds, [$id]))
            : [...$this->participantIds, $id];
    }

    public function openMeeting(int $id): void
    {
        $query = Meeting::with(['users:id,name', 'creator:id,name']);

        if (! hms_can('meetings.manage')) {
            $query->where(function ($q) {
                $q->where('created_by', auth()->id())
                    ->orWhereHas('participants', fn ($p) => $p->where('user_id', auth()->id()));
            });
        }

        $meeting = $query->findOrFail($id);

        $this->meetingId = $meeting->id;
        $this->meetingTitle = $meeting->title;
        $this->meetingWhen = $meeting->scheduled_at->format('d M Y, h:i A');
        $this->meetingLocation = $meeting->location;
        $this->meetingDuration = $meeting->duration_minutes;
        $this->meetingStatus = $meeting->status;
        $this->meetingAgenda = $meeting->agenda;
        $this->meetingParticipants = $meeting->users->pluck('name')->all();
        $this->meetingIsOrganiser = $meeting->created_by === auth()->id();
        $this->meetingMyResponse = $this->meetingIsOrganiser
            ? null
            : ($meeting->users->firstWhere('id', auth()->id())?->pivot?->response ?? 'pending');
    }

    public function closeMeeting(): void
    {
        $this->meetingId = null;
    }

    public function respond(int $id, string $response): void
    {
        abort_unless(in_array($response, ['accepted', 'declined'], true), 422);

        $meeting = Meeting::findOrFail($id);

        abort_unless(
            $meeting->created_by === auth()->id()
                || $meeting->participants()->where('user_id', auth()->id())->exists(),
            403
        );

        abort_if($meeting->status !== 'scheduled', 422, 'This meeting is no longer open for responses.');

        MeetingParticipant::updateOrCreate(
            ['meeting_id' => $meeting->id, 'user_id' => auth()->id()],
            ['response' => $response]
        );

        if ($this->meetingId === $meeting->id) {
            $this->meetingMyResponse = $response;
        }

        session()->flash('success', 'Response saved for "'.$meeting->title.'".');
    }

    public function cancelMeeting(int $id): void
    {
        if (! hms_can('meetings.manage')) {
            abort(403);
        }

        Meeting::findOrFail($id)->update(['status' => 'cancelled']);

        $this->meetingId = null;

        session()->flash('success', 'Meeting cancelled.');
    }
}