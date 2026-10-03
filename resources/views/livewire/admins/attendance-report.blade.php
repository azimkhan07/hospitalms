<div class="box box-primary">
    <div class="box-header d-flex align-items-center justify-content-between flex-wrap" style="gap:.5rem">
        <strong style="font-size:13px">Presency report &mdash; {{ $monthStart->format('F Y') }}</strong>
        <a href="{{ route('admin_attendance') }}" class="btn btn-xs btn-outline-secondary">
            <i class="fas fa-list"></i> Day register
        </a>
    </div>

    <div class="box-body">
        <div class="row mb-2">
            <div class="col-md-3 col-6">
                <label class="mb-1" style="font-size:11px">Month</label>
                <input type="month" class="form-control form-control-sm" wire:model.live="month">
            </div>
            <div class="col-md-3 col-6">
                <label class="mb-1" style="font-size:11px">Role</label>
                <select class="form-control form-control-sm" wire:model.live="role">
                    <option value="">All roles</option>
                    @foreach ($roles as $r)
                        <option value="{{ $r->slug }}">{{ $r->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="row mb-2">
            @php
                $tiles = [
                    ['Staff', $summary['staff'], 'secondary'],
                    ['Present days', $summary['present'], 'success'],
                    ['Half days', $summary['halfDay'], 'warning'],
                    ['Absent sessions', $summary['absent'], 'danger'],
                    ['Total hours', $summary['hours'], 'info'],
                ];
            @endphp
            @foreach ($tiles as [$label, $value, $colour])
                <div class="col">
                    <div class="border rounded p-2 text-center">
                        <div class="text-muted" style="font-size:10.5px">{{ $label }}</div>
                        <div class="badge badge-{{ $colour }} mt-1" style="font-size:12px">{{ $value }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0" style="font-size:11px">
                <thead>
                    <tr>
                        <th rowspan="2" class="align-middle">Staff</th>
                        <th colspan="{{ $monthStart->daysInMonth }}" class="text-center" style="font-size:10px">
                            {{ $monthStart->format('F Y') }}
                        </th>
                        <th rowspan="2" class="text-center align-middle">P</th>
                        <th rowspan="2" class="text-center align-middle">HD</th>
                        <th rowspan="2" class="text-center align-middle">A</th>
                        <th rowspan="2" class="text-center align-middle">Hrs</th>
                    </tr>
                    <tr>
                        @for ($d = 1; $d <= $monthStart->daysInMonth; $d++)
                            <th class="text-center p-1" style="font-size:9px">{{ $d }}</th>
                        @endfor
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report as $row)
                        <tr>
                            <td class="text-nowrap">
                                {{ $row['user']->name }}
                                <span class="text-muted d-block" style="font-size:9.5px">{{ hms_role_label($row['user']) }}</span>
                            </td>
                            @foreach ($row['days'] as $day)
                                @php
                                    $cell = $day['status'];
                                    $title = match ($cell) {
                                        'present' => 'Present',
                                        'half_day' => 'Half day',
                                        'absent' => 'Absent',
                                        default => 'Weekly off',
                                    };
                                    if ($day['in']) {
                                        $title .= ' | in '.$day['in']->format('h:i A');
                                    }
                                    if ($day['out']) {
                                        $title .= ' | out '.$day['out']->format('h:i A');
                                    }
                                    if ($day['worked'] !== null) {
                                        $title .= ' | '.intdiv($day['worked'], 60).'h '.($day['worked'] % 60).'m';
                                    }
                                    $mark = ['present' => 'P', 'half_day' => 'H', 'absent' => 'A'][$cell] ?? '';
                                    $shade = ['present' => '#d4edda', 'half_day' => '#fff3cd', 'absent' => '#f8d7da'][$cell] ?? '#f8f9fa';
                                @endphp
                                <td class="text-center p-1" title="{{ $title }}"
                                    style="background:{{ $shade }};font-size:9.5px">{{ $mark }}</td>
                            @endforeach
                            <td class="text-center">{{ $row['present'] }}</td>
                            <td class="text-center">{{ $row['half_day'] }}</td>
                            <td class="text-center">{{ $row['absent'] }}</td>
                            <td class="text-center">{{ $row['hours'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $monthStart->daysInMonth + 5 }}" class="text-center text-muted py-3">
                                No active staff for this tenant.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="text-muted mt-2 mb-0" style="font-size:10.5px">
            P = present ({{ $rules['full_day_hours'] }}h+), H = half day
            ({{ $rules['half_day_hours'] }}h), A = below that. Blank means no sign-in recorded.
        </p>
    </div>
</div>
