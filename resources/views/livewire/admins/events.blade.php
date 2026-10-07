<div>
<div class="row">
    <div class="col-xl-8">
        <div class="box box-primary">
            <div class="box-header d-flex align-items-center justify-content-between">
                <h3 class="box-title">
                    <i class="fas fa-calendar-alt text-info mr-1"></i>
                    Events &amp; Camps &mdash; {{ $month->format('F Y') }}
                </h3>
                <div class="d-flex align-items-center">
                    <button type="button" class="btn btn-xs btn-outline-secondary mr-1"
                        wire:click="shiftMonth(-1)"><i class="fas fa-chevron-left"></i></button>
                    <button type="button" class="btn btn-xs btn-outline-secondary mr-1"
                        wire:click="gotoToday">Today</button>
                    <button type="button" class="btn btn-xs btn-outline-secondary"
                        wire:click="shiftMonth(1)"><i class="fas fa-chevron-right"></i></button>
                    <button type="button" class="btn btn-xs btn-primary ml-2"
                        wire:click="resetForm">
                        <i class="fas fa-plus"></i> New Event
                    </button>
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
                            $dayEvents = $byDate->get($key, collect());
                            $dayMeetings = $meetingsByDate->get($key, collect());
                            $isToday = $day->isToday();
                        @endphp
                        <div class="hms-cell {{ $day->month !== $month->month ? 'muted' : '' }} {{ $isToday ? 'today' : '' }}"
                            wire:click="selectDate('{{ $key }}')"
                            style="cursor:pointer">
                            <div class="hms-cell-date">{{ $day->day }}</div>
                            @foreach ($dayMeetings as $m)
                                <div class="hms-chip hms-ev-meeting"
                                    title="Meeting &middot; {{ $m->title }}">
                                    <span class="hms-chip-time">{{ $m->scheduled_at->format('h:i A') }}</span>
                                    <span class="hms-chip-title">{{ $m->title }}</span>
                                </div>
                            @endforeach
                            @foreach ($dayEvents as $e)
                                <div class="hms-chip hms-ev-event"
                                    style="background:{{ $e->color }};border-color:{{ $e->color }}"
                                    title="{{ $e->typeLabel() }} &middot; {{ $e->starts_at->format('h:i A') }}"
                                    wire:click.stop="openEvent({{ $e->id }})">
                                    <span class="hms-chip-time">{{ $e->starts_at->format('h:i A') }}</span>
                                    <span class="hms-chip-title">{{ $e->title }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
                <div class="hms-legend">
                    <span><i class="dot" style="background:#98a4b3"></i> Meeting</span>
                    <span><i class="dot" style="background:#d9534f"></i> Blood camp</span>
                    <span><i class="dot" style="background:#5cb85c"></i> Visiting doctor</span>
                    <span><i class="dot" style="background:#0f7fd4"></i> Hospital event</span>
                </div>
            </div>
        </div>

        @if ($upcoming->count())
            <div class="box box-primary">
                <div class="box-header">
                    <h3 class="box-title"><i class="fas fa-bullhorn text-info mr-1"></i> Upcoming</h3>
                </div>
                <div class="box-body p-0">
                    @foreach ($upcoming as $e)
                        <button type="button" class="hms-ev-row" wire:click="openEvent({{ $e->id }})">
                            <span class="hms-ev-stripe" style="background:{{ $e->color }}"></span>
                            <span class="hms-ev-body">
                                <strong>{{ $e->title }}</strong>
                                <span class="text-muted">{{ $e->typeLabel() }} &middot; {{ $e->starts_at->format('d M Y, h:i A') }}</span>
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div class="col-xl-4">
        <div class="box box-primary">
            <div class="box-header">
                <h3 class="box-title">
                    <i class="fas fa-calendar-plus text-info mr-1"></i>
                    {{ $editingId ? 'Edit Event' : 'New Event' }}
                </h3>
            </div>
            <div class="box-body">
                <form wire:submit.prevent="save">
                    <div class="form-group">
                        <label for="eventTitle">Title</label>
                        <input id="eventTitle" type="text" class="form-control form-control-sm"
                            placeholder="e.g. Free Heart Checkup Camp" wire:model="title">
                        @error('title')
                            <span class="text-danger" style="font-size:11px">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label for="eventType">Type</label>
                        <select id="eventType" class="form-control form-control-sm" wire:model="type">
                            <option value="blood_camp">Blood donation camp</option>
                            <option value="visiting">Visiting doctor</option>
                            <option value="general">Hospital event</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-6">
                            <label for="eventStartsOn">Date</label>
                            <input id="eventStartsOn" type="date" class="form-control form-control-sm"
                                wire:model="startsOn">
                            @error('startsOn')
                                <span class="text-danger" style="font-size:11px">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group col-6">
                            <label for="eventStartsAt">Time</label>
                            <input id="eventStartsAt" type="time" class="form-control form-control-sm"
                                wire:model="startsAt">
                            @error('startsAt')
                                <span class="text-danger" style="font-size:11px">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="eventDesc">Description</label>
                        <textarea id="eventDesc" rows="2" class="form-control form-control-sm"
                            placeholder="Optional details" wire:model="description"></textarea>
                    </div>
                    @if ($editingId)
                        <div class="d-flex">
                            <button type="submit" class="btn btn-sm btn-primary mr-1 flex-fill">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger"
                                onclick="return confirm('Remove this event?')"
                                wire:click="removeEvent({{ $editingId }})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <a href="#" wire:click.prevent="resetForm" class="d-block mt-2 text-muted"
                            style="font-size:11.5px">Cancel editing</a>
                    @else
                        <button type="submit" class="btn btn-sm btn-primary w-100">
                            <i class="fas fa-paper-plane"></i> Schedule Event
                        </button>
                    @endif
                </form>

                @if ($canManage)
                    <div class="mt-3 pt-3 border-top text-muted" style="font-size:11px">
                        Click any grid day to pre-fill the date, or click a coloured
                        chip to edit that event.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<style>
    .hms-chip.hms-ev-meeting {
        background: #98a4b3;
        cursor: default;
    }
    .hms-chip.hms-ev-event { cursor: pointer; }
    .hms-ev-row {
        display: flex;
        width: 100%;
        text-align: left;
        border: 0;
        border-bottom: 1px solid #eef2f7;
        background: #fff;
        padding: 0;
    }
    .hms-ev-row:last-child { border-bottom: 0; }
    .hms-ev-stripe {
        width: 4px;
        flex: 0 0 4px;
        align-self: stretch;
    }
    .hms-ev-body {
        display: flex;
        flex-direction: column;
        padding: .45rem .65rem;
        font-size: 12px;
        gap: .1rem;
    }
</style>
</div>