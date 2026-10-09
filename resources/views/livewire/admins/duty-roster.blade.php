<div class="box box-primary">
    <div class="box-header d-flex align-items-center justify-content-between flex-wrap" style="gap:.5rem">
        <strong style="font-size:13px">
            Duty Roster &mdash; {{ $days->first()->format('d M') }} to {{ $weekEnd->format('d M Y') }}
        </strong>
        <div class="d-flex align-items-center" style="gap:.4rem">
            <button type="button" class="btn btn-xs btn-outline-secondary" wire:click="previousWeek">
                <i class="fas fa-chevron-left"></i> Prev
            </button>
            <button type="button" class="btn btn-xs btn-outline-secondary" wire:click="currentWeek">
                This week
            </button>
            <button type="button" class="btn btn-xs btn-outline-secondary" wire:click="nextWeek">
                Next <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>

    <div class="box-body">
        @if (session('message'))
            <div class="alert alert-success py-1 px-2 mb-2" style="font-size:12px">{{ session('message') }}</div>
        @endif
        @error('doctorId')
            <div class="alert alert-danger py-1 px-2 mb-2" style="font-size:12px">{{ $message }}</div>
        @enderror

        <div class="row mb-2">
            <div class="col-md-3 col-6">
                <label class="mb-1" style="font-size:11px">Filter doctor</label>
                <select class="form-control form-control-sm" wire:model.live="doctorFilter">
                    <option value="">All doctors</option>
                    @foreach ($doctorOptions as $d)
                        <option value="{{ $d->id }}">{{ $d->employ?->name ?? 'Doctor #'.$d->id }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="border rounded p-2 mb-2">
            <form wire:submit="save" class="row" style="row-gap:.4rem">
                <div class="col-md-3 col-6">
                    <label class="mb-1" style="font-size:11px">Doctor</label>
                    <select class="form-control form-control-sm" wire:model="doctorId">
                        <option value="">&mdash; department row &mdash;</option>
                        @foreach ($doctorOptions as $d)
                            <option value="{{ $d->id }}">{{ $d->employ?->name ?? 'Doctor #'.$d->id }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-6">
                    <label class="mb-1" style="font-size:11px">Department</label>
                    <input type="text" class="form-control form-control-sm" placeholder="e.g. Emergency"
                        wire:model="department">
                </div>
                <div class="col-md-2 col-6">
                    <label class="mb-1" style="font-size:11px">Date</label>
                    <input type="date" class="form-control form-control-sm" wire:model="dutyDate">
                </div>
                <div class="col-md-2 col-6">
                    <label class="mb-1" style="font-size:11px">Shift</label>
                    <select class="form-control form-control-sm" wire:model="shift">
                        @foreach (\App\Models\DutyRoster::SHIFTS as $s)
                            <option value="{{ $s }}">{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1 col-6">
                    <label class="mb-1" style="font-size:11px">Start</label>
                    <input type="time" class="form-control form-control-sm" wire:model="startTime">
                </div>
                <div class="col-md-1 col-6">
                    <label class="mb-1" style="font-size:11px">End</label>
                    <input type="time" class="form-control form-control-sm" wire:model="endTime">
                </div>
                <div class="col-md-1 col-12 d-flex align-items-end">
                    <button type="submit" class="btn btn-xs btn-primary btn-block">Assign</button>
                </div>
                <div class="col-12">
                    <input type="text" class="form-control form-control-sm" placeholder="Note (optional)"
                        wire:model="note">
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0" style="font-size:11.5px">
                <thead>
                    <tr>
                        <th style="min-width:150px">Staff</th>
                        @foreach ($days as $day)
                            <th class="text-center {{ $day->isToday() ? 'bg-light' : '' }}">
                                {{ $day->format('D') }}<br><span class="text-muted">{{ $day->format('d M') }}</span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($doctors as $doc)
                        <tr>
                            <td>{{ $doc->employ?->name ?? 'Doctor #'.$doc->id }}</td>
                            @foreach ($days as $day)
                                @php
                                    $row = $entries[$doc->id][$day->format('Y-m-d')] ?? null;
                                    $colour = [
                                        'morning' => 'info',
                                        'evening' => 'warning',
                                        'night' => 'dark',
                                        'off' => 'secondary',
                                    ][$row->shift ?? 'off'] ?? 'secondary';
                                @endphp
                                <td class="text-center {{ $day->isToday() ? 'bg-light' : '' }}">
                                    @if ($row)
                                        <span class="badge badge-{{ $colour }}">{{ ucfirst($row->shift) }}</span>
                                        @if ($row->start_time)
                                            <div class="text-muted" style="font-size:10px">
                                                {{ $row->start_time }}@if ($row->end_time) - {{ $row->end_time }}@endif
                                            </div>
                                        @endif
                                        <button type="button" class="btn btn-xs btn-link text-danger p-0"
                                            wire:click="remove({{ $row->id }})"
                                            wire:confirm="Remove this shift?">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    @else
                                        <span class="text-muted">&mdash;</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-3">No doctors match this filter.</td>
                        </tr>
                    @endforelse

                    @foreach ($departmentEntries as $dept => $byDay)
                        <tr>
                            <td><em>{{ $dept }} (department)</em></td>
                            @foreach ($days as $day)
                                @php
                                    $row = $byDay[$day->format('Y-m-d')] ?? null;
                                    $colour = [
                                        'morning' => 'info',
                                        'evening' => 'warning',
                                        'night' => 'dark',
                                        'off' => 'secondary',
                                    ][$row->shift ?? 'off'] ?? 'secondary';
                                @endphp
                                <td class="text-center {{ $day->isToday() ? 'bg-light' : '' }}">
                                    @if ($row)
                                        <span class="badge badge-{{ $colour }}">{{ ucfirst($row->shift) }}</span>
                                        <button type="button" class="btn btn-xs btn-link text-danger p-0"
                                            wire:click="remove({{ $row->id }})"
                                            wire:confirm="Remove this shift?">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    @else
                                        <span class="text-muted">&mdash;</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
