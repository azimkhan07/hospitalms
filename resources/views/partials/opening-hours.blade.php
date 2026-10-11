@php
    $hmsHours = hms_weekly_hours();
    $hmsToday = hms_today_hours();
    $hmsNote = $site['hours_note'] ?? null;
@endphp
<div class="hms-hours">
    <div class="hms-hours-today {{ $hmsToday['closed'] ? 'is-closed' : 'is-open' }}">
        <i class="fa fa-clock-o"></i>
        @if ($hmsToday['closed'])
            Closed today
            @if ($hmsToday['next_label'])
                <span class="hms-hours-next">&middot; Next: {{ $hmsToday['next_label'] }} {{ $hmsToday['next_value'] }}</span>
            @endif
        @else
            Open today {{ $hmsToday['value'] }}
        @endif
    </div>
    <ul class="hms-hours-list">
        @foreach ($hmsHours as $h)
            <li class="{{ $h['key'] === $hmsToday['key'] ? 'is-today' : '' }}{{ $h['closed'] ? ' is-closed' : '' }}">
                <span class="hms-hours-day">{{ $h['label'] }}</span>
                <span class="hms-hours-time">{{ $h['closed'] ? 'Closed' : $h['value'] }}</span>
            </li>
        @endforeach
    </ul>
    @if ($hmsNote)
        <p class="hms-hours-note"><i class="fa fa-info-circle"></i> {{ $hmsNote }}</p>
    @endif
</div>