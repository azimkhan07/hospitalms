<div class="nav-item dropdown hms-bell" wire:poll.30s>
    <a href="#" class="nav-link" wire:click.prevent="toggle" title="Meetings">
        <i class="fas fa-video"></i>
        @if ($this->liveCount > 0)
            <span class="hms-bell-dot">{{ $this->liveCount }}</span>
        @endif
    </a>

    @if ($open)
        <div class="dropdown-menu dropdown-menu-right hms-bell-menu show">
            <div class="hms-bell-head">
                <strong>Meetings</strong>
                <a href="{{ route('admin_meetings') }}" class="btn btn-xs btn-link p-0" style="font-size:10.5px">
                    Open calendar
                </a>
            </div>

            @forelse ($this->meetings as $m)
                @php
                    $joinable = $m->canJoin(auth()->user());
                    $isHost = $m->isHost(auth()->user());
                    $started = $m->hasStarted();
                @endphp
                <div class="hms-bell-row" wire:key="mt{{ $m->id }}">
                    <i class="fas fa-video {{ $joinable ? 'text-success' : ($started ? 'text-warning' : 'text-info') }}"></i>
                    <span style="flex:1;min-width:0">
                        <strong class="d-block text-truncate">{{ $m->title }}</strong>
                        <small class="text-muted d-block">
                            {{ $m->scheduled_at->format('d M, h:i A') }}
                            &middot; {{ $m->duration_minutes }}m
                            @if ($m->host) &middot; {{ $m->host->name }} @endif
                        </small>

                        @if ($joinable)
                            <span class="badge badge-success badge-sm">Live now</span>
                        @elseif ($started)
                            <span class="badge badge-warning badge-sm">In progress</span>
                        @else
                            <span class="text-muted" style="font-size:10.5px"
                                x-data="{ left: {{ max(0, now()->diffInSeconds($m->scheduled_at, false)) }} }"
                                x-init="setInterval(() => { if (left > 0) left-- }, 1000)">
                                Starts in
                                <span x-text="left <= 0 ? 'now' : (left >= 3600
                                    ? Math.floor(left / 3600) + 'h ' + Math.floor((left % 3600) / 60) + 'm'
                                    : (left >= 60 ? Math.floor(left / 60) + 'm ' + (left % 60) + 's' : left + 's'))"></span>
                            </span>
                        @endif
                    </span>

                    @if ($joinable)
                        <a href="{{ $m->roomPath() }}" class="btn btn-xs btn-success">Join</a>
                    @elseif ($isHost)
                        <button type="button" class="btn btn-xs btn-primary"
                            wire:click="startNow({{ $m->id }})">Start</button>
                    @else
                        <button type="button" class="btn btn-xs btn-outline-secondary" disabled>Join</button>
                    @endif
                </div>
            @empty
                <div class="hms-bell-row text-muted">No meetings scheduled.</div>
            @endforelse
        </div>
    @endif
</div>
