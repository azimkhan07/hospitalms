<div>
    <div class="row">
        @foreach ($cards as $card)
            <div class="col-lg-2 col-md-4 col-6 mb-2">
                <div class="box box-primary hms-report-kpi" style="margin-bottom:0">
                    <div class="box-body d-flex align-items-center" style="padding:.55rem .65rem">
                        <div class="hms-kpi-icon" style="background:{{ $card['color'] }}"><i class="fas {{ $card['icon'] }}"></i></div>
                        <div class="ml-2">
                            <div class="hms-kpi-value">{{ $card['value'] }}</div>
                            <div class="hms-kpi-label">{{ $card['label'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

@if ($newsletters->count())
    <div class="mt-2">
        <div class="box box-primary">
            <div class="box-header">
                <h3 class="box-title"><i class="fas fa-bullhorn text-info mr-1"></i> Newsletter</h3>
            </div>
            <div class="box-body p-0">
                @foreach ($newsletters as $n)
                    <div class="hms-meeting-row">
                        <div class="d-flex justify-content-between align-items-start">
                            <strong>
                                <i class="fas {{ $n->icon() }} text-info mr-1"></i>
                                {{ $n->title }}
                                @if ($n->important) <span class="badge badge-sm badge-danger">Important</span> @endif
                            </strong>
                            <small class="text-muted ml-2" style="white-space:nowrap">{{ $n->created_at->diffForHumans() }}</small>
                        </div>
                        @if ($n->body)
                            <div class="text-muted" style="font-size:11.5px">{{ $n->body }}</div>
                        @endif
                        @if ($n->meeting)
                            <div class="text-muted" style="font-size:11px">
                                <i class="far fa-clock mr-1"></i>{{ $n->meeting->scheduled_at->format('d M Y, h:i A') }}
                                @php $joinable = $n->meeting->canJoin(auth()->user()); @endphp
                                @if ($joinable)
                                    &middot; <a href="{{ $n->meeting->roomPath() }}" class="text-success font-weight-bold">Join now</a>
                                @elseif ($n->meeting->hasStarted())
                                    &middot; <span class="text-warning">in progress</span>
                                @else
                                    &middot; <span x-data="{ left: {{ max(0, now()->diffInSeconds($n->meeting->scheduled_at, false)) }} }"
                                        x-init="setInterval(() => { if (left > 0) left-- }, 1000)">
                                        starts in <span x-text="left <= 0 ? 'now' : (left >= 3600
                                            ? Math.floor(left / 3600) + 'h ' + Math.floor((left % 3600) / 60) + 'm'
                                            : (left >= 60 ? Math.floor(left / 60) + 'm ' + (left % 60) + 's' : left + 's'))"></span>
                                    </span>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif

@if (hms_can('meetings'))
    <div class="mt-2">
        <h4 class="mb-1"><i class="fas fa-calendar-alt text-info mr-1"></i> Meeting Calendar</h4>
        <livewire:admins.meeting-calendar :key="'dash-calendar'" />
    </div>
@endif

@if (hms_can('leave'))
    <div class="mt-2">
        <div class="box box-primary">
            <div class="box-header">
                <h3 class="box-title"><i class="fas fa-plane-departure text-info mr-1"></i> Leave Snapshot</h3>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="border rounded p-2 text-center">
                            <div class="text-muted" style="font-size:10.5px">PENDING</div>
                            <div style="font-size:18px;font-weight:700">{{ $pendingLeave }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-2 text-center">
                            <div class="text-muted" style="font-size:10.5px">MY APPROVED</div>
                            <div style="font-size:18px;font-weight:700">{{ $myApprovedLeave }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-2 text-center">
                            <div class="text-muted" style="font-size:10.5px">MY UPCOMING MEETINGS</div>
                            <div style="font-size:18px;font-weight:700">{{ $myMeetings }}</div>
                        </div>
                    </div>
                </div>

                @if (hms_can('leave.review') && $pendingLeave > 0)
                    <div class="mt-2" style="font-size:11.5px">
                        <i class="fas fa-bell text-warning mr-1"></i>
                        {{ $pendingLeave }} leave request(s) are waiting for your decision.
                        <a href="{{ route('admin_leave') }}" class="ml-1">Open review queue &rarr;</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
</div>