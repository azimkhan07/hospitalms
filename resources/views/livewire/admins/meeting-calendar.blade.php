<div class="row">
    <div class="col-xl-8">
        <div class="box box-primary hms-meetings">
            <div class="box-header d-flex align-items-center justify-content-between">
                <h3 class="box-title">
                    <i class="fas fa-calendar-alt text-info mr-1"></i>
                    {{ $month->format('F Y') }}
                </h3>
                <div class="d-flex align-items-center">
                    <button type="button" class="btn btn-xs btn-outline-secondary mr-1"
                        wire:click="shiftMonth(-1)"><i class="fas fa-chevron-left"></i></button>
                    <button type="button" class="btn btn-xs btn-outline-secondary mr-1"
                        wire:click="gotoToday">Today</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary"
                        wire:click="shiftMonth(1)"><i class="fas fa-chevron-right"></i></button>
                    @if (hms_can('meetings.manage'))
                        <button type="button" class="btn btn-xs btn-primary ml-2"
                            wire:click="togglePicker">
                            <i class="fas fa-plus"></i> Schedule
                        </button>
                    @endif
                </div>
            </div>
            <div class="box-body">
                <div class="hms-weekdays">
                    @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $d)
                        <div class="hms-weekday">{{ $d }}</div>
                    @endforeach
                </div>
                <div class="hms-grid">
                    @foreach ($gridStart->toPeriod($gridEnd, '1 day') as $day)
                        @php
                            $key = $day->format('Y-m-d');
                            $dayMeetings = $byDate->get($key, collect());
                            $isToday = $day->isToday();
                            $isSelected = $selectedDate === $key;
                        @endphp
                        <div class="hms-cell {{ $day->month !== $month->month ? 'muted' : '' }} {{ $isToday ? 'today' : '' }} {{ $isSelected ? 'selected' : '' }}">
                            <div class="hms-cell-date">{{ $day->day }}</div>
                            @foreach ($dayMeetings as $m)
                                <div class="hms-chip {{ $m->status }}"
                                    title="{{ $m->title }} &middot; {{ $m->scheduled_at->format('h:i A') }}"
                                    @if ($m->status === 'scheduled') wire:click="openMeeting({{ $m->id }})" @endif>
                                    <span class="hms-chip-time">{{ $m->scheduled_at->format('h:i A') }}</span>
                                    <span class="hms-chip-title">{{ $m->title }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>

                @if (count($byDate))
                    <div class="hms-legend">
                        <span><i class="dot scheduled"></i> Scheduled</span>
                        <span><i class="dot completed"></i> Completed</span>
                        <span><i class="dot cancelled"></i> Cancelled</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        @if (hms_can('meetings.manage'))
            <div class="box box-primary">
                <div class="box-header">
                    <h3 class="box-title"><i class="fas fa-calendar-plus text-info mr-1"></i> New Meeting</h3>
                </div>
                <div class="box-body">
                    <form wire:submit.prevent="saveMeeting">
                        <div class="form-group">
                            <label for="meetingDate">Meeting Date</label>
                            <input id="meetingDate" type="date" class="form-control form-control-sm"
                                wire:model="selectedDate">
                            @error('selectedDate')
                                <span class="text-danger" style="font-size:11px">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="meetingTitle">Title</label>
                            <input id="meetingTitle" type="text" class="form-control form-control-sm"
                                placeholder="e.g. Morning Huddle" wire:model="title">
                            @error('title')
                                <span class="text-danger" style="font-size:11px">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="meetingLocation">Location</label>
                            <input id="meetingLocation" type="text" class="form-control form-control-sm"
                                wire:model="location">
                            @error('location')
                                <span class="text-danger" style="font-size:11px">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label for="meetingDuration">Duration</label>
                                <select id="meetingDuration" class="form-control form-control-sm" wire:model="duration">
                                    @foreach ([15, 30, 45, 60, 90, 120] as $d)
                                        <option value="{{ $d }}">{{ $d }} min</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-6">
                                <label for="meetingTime">Time</label>
                                <input id="meetingTime" type="time" class="form-control form-control-sm" wire:model="time">
                                @error('time')
                                    <span class="text-danger" style="font-size:11px">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="meetingAgenda">Agenda</label>
                            <textarea id="meetingAgenda" rows="2" class="form-control form-control-sm"
                                placeholder="Optional agenda" wire:model="agenda"></textarea>
                        </div>
                        <div class="form-group mb-1">
                            <label>Participants <span class="text-danger">*</span></label>
                            <input type="search" class="form-control form-control-sm mb-1"
                                placeholder="Search staff..." wire:model.live="participantSearch">
                            <div class="hms-participants">
                                @foreach ($users as $u)
                                    @php $on = in_array($u->id, $participantIds, true); @endphp
                                    <button type="button"
                                        class="hms-pill {{ $on ? 'on' : '' }}"
                                        wire:click="toggleParticipant({{ $u->id }})">
                                        <i class="fas fa-user"></i>
                                        {{ $u->name }}
                                        <small>{{ $u->role?->name }}</small>
                                    </button>
                                @endforeach
                            </div>
                            @error('participantIds')
                                <span class="text-danger" style="font-size:11px">Pick at least one participant.</span>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary w-100">
                            <i class="fas fa-paper-plane"></i> Schedule &amp; Notify
                        </button>
                    </form>
                </div>
            </div>
        @endif

        <div class="box box-primary">
            <div class="box-header">
                <h3 class="box-title"><i class="fas fa-inbox text-info mr-1"></i> My Meetings</h3>
            </div>
            <div class="box-body p-0">
                @forelse ($mine as $m)
                    <div class="hms-meeting-row">
                        <div class="d-flex justify-content-between">
                            <strong>{{ $m->title }}</strong>
                            @php
                                $minePivot = $m->users->firstWhere('id', auth()->id())?->pivot?->response;
                                $isOrganiser = $m->created_by === auth()->id();
                                $awaiting = ! $isOrganiser && ($minePivot === 'pending' || ! $minePivot);
                            @endphp
                            @if ($isOrganiser)
                                <span class="badge badge-sm badge-info">Organiser</span>
                            @elseif ($minePivot && $minePivot !== 'pending')
                                <span class="badge badge-sm {{ $minePivot === 'accepted' ? 'badge-success' : 'badge-danger' }}">
                                    {{ ucfirst($minePivot) }}
                                </span>
                            @else
                                <span class="badge badge-sm badge-warning">Awaiting reply</span>
                            @endif
                        </div>
                        <div class="text-muted" style="font-size:11px">
                            {{ $m->scheduled_at->format('d M Y, h:i A') }}
                            @if ($m->location) &middot; {{ $m->location }} @endif
                            &middot; {{ $m->duration_minutes }} min
                        </div>
                        <div class="text-muted" style="font-size:11px">
                            {{ $m->users->where('id', '!=', auth()->id())->pluck('name')->join(', ') ?: 'Only you' }}
                        </div>
                        @if ($awaiting)
                            <div class="mt-1 d-flex">
                                <button type="button" class="btn btn-xs btn-outline-success mr-1"
                                    wire:click="respond({{ $m->id }}, 'accepted')">
                                    <i class="fas fa-check"></i> Accept
                                </button>
                                <button type="button" class="btn btn-xs btn-outline-danger"
                                    wire:click="respond({{ $m->id }}, 'declined')">
                                    <i class="fas fa-times"></i> Decline
                                </button>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="p-3 text-muted" style="font-size:11.5px">No upcoming meetings.</div>
                @endforelse
            </div>
        </div>
    </div>

    @if ($showPicker)
        <div class="hms-picker-backdrop" wire:click.self="togglePicker">
            <div class="hms-picker">
                <div class="hms-picker-head">
                    <strong>Pick a date &mdash; {{ $month->format('F Y') }}</strong>
                    <button type="button" class="close" wire:click="togglePicker" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="hms-weekdays">
                    @foreach (['S', 'M', 'T', 'W', 'T', 'F', 'S'] as $d)
                        <div class="hms-weekday">{{ $d }}</div>
                    @endforeach
                </div>
                <div class="hms-grid compact">
                    @foreach ($gridStart->toPeriod($gridEnd, '1 day') as $day)
                        <button type="button"
                            class="hms-cell {{ $day->month !== $month->month ? 'muted' : '' }} {{ $day->isToday() ? 'today' : '' }}"
                            wire:click="selectDate('{{ $day->format('Y-m-d') }}')">
                            {{ $day->day }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if ($meetingId)
        <div class="hms-picker-backdrop" wire:click.self="closeMeeting">
            <div class="hms-picker" style="max-width:420px">
                <div class="hms-picker-head">
                    <strong>{{ $meetingTitle }}</strong>
                    <button type="button" class="close" wire:click="closeMeeting" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="p-3" style="font-size:12px">
                    <div><i class="far fa-clock mr-1"></i>{{ $meetingWhen ?: 'Not scheduled' }}</div>
                    <div><i class="fas fa-map-marker-alt mr-1"></i>{{ $meetingLocation ?: 'Location not set' }}</div>
                    <div><i class="fas fa-hourglass-half mr-1"></i>{{ $meetingDuration ?: 0 }} minutes</div>
                    <div class="mt-2">
                        <i class="fas fa-users mr-1"></i>
                        {{ $meetingParticipants ? implode(', ', $meetingParticipants) : 'No participants' }}
                    </div>
                    @if ($meetingAgenda)
                        <div class="mt-2 p-2 bg-light rounded">{{ $meetingAgenda }}</div>
                    @endif
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <span class="badge {{ $meetingStatus === 'scheduled' ? 'badge-info' : ($meetingStatus === 'completed' ? 'badge-success' : 'badge-secondary') }}">
                            {{ ucfirst($meetingStatus) }}
                        </span>
                        @if (hms_can('meetings.manage') && $meetingStatus === 'scheduled')
                            <button type="button" class="btn btn-xs btn-outline-danger"
                                wire:click="cancelMeeting({{ $meetingId }})">Cancel Meeting</button>
                        @endif
                    </div>

                    @if (! $meetingIsOrganiser && $meetingStatus === 'scheduled')
                        <div class="mt-2 pt-2 border-top">
                            @if ($meetingMyResponse === 'pending')
                                <div class="text-muted mb-1" style="font-size:11.5px">Let the organiser know:</div>
                                <div class="d-flex">
                                    <button type="button" class="btn btn-xs btn-success mr-1"
                                        wire:click="respond({{ $meetingId }}, 'accepted')">
                                        <i class="fas fa-check"></i> Accept
                                    </button>
                                    <button type="button" class="btn btn-xs btn-outline-danger"
                                        wire:click="respond({{ $meetingId }}, 'declined')">
                                        <i class="fas fa-times"></i> Decline
                                    </button>
                                </div>
                            @else
                                <span class="badge badge-sm {{ $meetingMyResponse === 'accepted' ? 'badge-success' : 'badge-danger' }}">
                                    You {{ $meetingMyResponse }} this meeting
                                </span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>