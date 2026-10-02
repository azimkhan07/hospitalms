<div class="nav-item dropdown hms-bell">
    <a href="#" class="nav-link" wire:click.prevent="toggle">
        <i class="fas fa-bell"></i>
        @if ($this->unreadCount > 0)
            <span class="hms-bell-dot">{{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}</span>
        @endif
    </a>

    @if ($open)
        <div class="dropdown-menu dropdown-menu-right hms-bell-menu show">
            <div class="hms-bell-head">
                <strong>Notifications</strong>
                @if ($this->unreadCount > 0)
                    <button type="button" class="btn btn-xs btn-link p-0" wire:click="markAllRead"
                        style="font-size:10.5px">Mark all read</button>
                @endif
            </div>

            @if ($this->pendingLeaveCount > 0)
                <a href="{{ route('admin_leave') }}" class="hms-bell-row bg-warning-lighter">
                    <i class="fas fa-clipboard-check text-warning"></i>
                    <span>
                        <strong>{{ $this->pendingLeaveCount }}</strong> leave request(s) waiting for review
                    </span>
                </a>
            @endif

            @forelse ($this->items as $item)
                <button type="button" class="hms-bell-row" wire:key="n{{ $item['id'] }}"
                    wire:click="markRead({{ $item['id'] }})">
                    <i class="fas {{ $item['icon'] }} {{ $item['colour'] }}"></i>
                    <span>
                        {{ $item['text'] }}
                        <small class="text-muted d-block">{{ $item['time'] }}</small>
                    </span>
                </button>
            @empty
                <div class="hms-bell-row text-muted">No notifications yet.</div>
            @endforelse
        </div>
    @endif
</div>