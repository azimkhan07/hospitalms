<div class="box box-primary">
    <div class="box-header d-flex align-items-center justify-content-between flex-wrap" style="gap:.5rem">
        <strong style="font-size:13px">Attendance &mdash; {{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}</strong>
        <div class="d-flex align-items-center" style="gap:.4rem">
            <a href="{{ route('admin_attendance_report') }}" class="btn btn-xs btn-outline-secondary">
                <i class="fas fa-chart-bar"></i> Monthly report
            </a>
        </div>
    </div>

    <div class="box-body">
        <div class="row mb-2">
            <div class="col-md-3 col-6">
                <label class="mb-1" style="font-size:11px">Date</label>
                <input type="date" class="form-control form-control-sm" wire:model.live="date">
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
            <div class="col-md-4 col-12">
                <label class="mb-1" style="font-size:11px">Search staff</label>
                <input type="text" class="form-control form-control-sm" placeholder="Name"
                    wire:model.live.debounce.400ms="search">
            </div>
        </div>

        <div class="row mb-2">
            @php
                $tiles = [
                    ['On duty', $stats['onDuty'], 'secondary'],
                    ['Present', $stats['present'], 'success'],
                    ['Half day', $stats['halfDay'], 'warning'],
                    ['Absent', $stats['absent'], 'danger'],
                    ['Still signed in', $stats['pending'], 'info'],
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

        <p class="text-muted mb-2" style="font-size:11px">
            Presence is worked out from sign-in and sign-out:
            {{ $rules['full_day_hours'] }}h or more = present,
            {{ $rules['half_day_hours'] }}h = half day, less = absent.
        </p>

        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0" style="font-size:11.5px">
                <thead>
                    <tr>
                        <th>Staff</th>
                        <th>Role</th>
                        <th>Sign in</th>
                        <th>Sign out</th>
                        <th>Worked</th>
                        <th>Distance</th>
                        <th>Presency</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php
                            $user = $row['user'] ?? $row['attendance']->user;
                            $a = $row['attendance'] ?? null;
                            $mins = $row['worked_minutes'];
                            $status = $row['status'];
                            $badge = [
                                'present' => 'success',
                                'half_day' => 'warning',
                                'absent' => 'danger',
                                'pending' => 'info',
                                'weekend' => 'light',
                            ][$status] ?? 'secondary';
                            $label = [
                                'present' => 'Present',
                                'half_day' => 'Half day',
                                'absent' => 'Absent',
                                'pending' => 'Signed in',
                                'weekend' => 'Weekly off',
                            ][$status] ?? $status;
                        @endphp
                        <tr>
                            <td>{{ $user?->name }}</td>
                            <td>{{ hms_role_label($user) }}</td>
                            <td>{{ $a?->check_in_at?->format('h:i A') ?? '-' }}</td>
                            <td>
                                @if ($a?->isOpen())
                                    <span class="badge badge-info">on duty</span>
                                @else
                                    {{ $a?->check_out_at?->format('h:i A') ?? '-' }}
                                @endif
                            </td>
                            <td>
                                @if ($mins === null)
                                    -
                                @else
                                    {{ intdiv($mins, 60) }}h {{ $mins % 60 }}m
                                @endif
                            </td>
                            <td>
                                @if (! is_null($a?->check_in_distance_meters))
                                    {{ $a->check_in_distance_meters }} m
                                @else
                                    -
                                @endif
                            </td>
                            <td><span class="badge badge-{{ $badge }}">{{ $label }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-3">No staff match this filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
