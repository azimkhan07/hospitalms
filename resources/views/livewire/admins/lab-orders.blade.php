<div class="content">
    <div class="container">
        <div class="page-title">
            <h3 class="text-info"><i class="fas fa-vials mr-1"></i> Lab Orders</h3>
        </div>

        @if (session()->has('message'))
            <div class="alert alert-success py-1 px-2">{{ session('message') }}</div>
        @endif
        @if (session()->has('error'))
            <div class="alert alert-danger py-1 px-2">{{ session('error') }}</div>
        @endif

        <div class="box box-primary">
            <div class="box-header d-flex align-items-center justify-content-between flex-wrap">
                <div>
                    @foreach ([
                        'pending' => 'Pending',
                        'in_progress' => 'In progress',
                        'reported' => 'Reported',
                        'all' => 'All',
                    ] as $key => $label)
                        <button class="btn btn-sm {{ $statusFilter === $key ? 'btn-info' : 'btn-outline-info' }} mr-1"
                            wire:click="$set('statusFilter', '{{ $key }}')">
                            {{ $label }}
                            @if ($key !== 'all')<span class="badge badge-light">{{ $counts[$key] ?? 0 }}</span>@endif
                        </button>
                    @endforeach
                    @if (($counts['critical'] ?? 0) > 0)
                        <span class="badge badge-danger ml-1">{{ $counts['critical'] }} critical</span>
                    @endif
                </div>
                <input type="search" class="form-control form-control-sm" style="max-width:200px"
                    placeholder="Search patient..." wire:model.live.debounce.300ms="search">
            </div>
            <div class="box-body">
                <table class="table table-sm table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Test</th>
                            <th>Patient</th>
                            <th>Source</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th style="width:140px" class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reports as $report)
                            <tr>
                                <td>{{ $report->id }}</td>
                                <td>
                                    {{ $report->test?->name ?? 'Test #'.$report->investigation_test_id }}
                                    <small class="text-muted d-block">{{ $report->test?->code }}</small>
                                    @if ($report->barcode)
                                        <small class="d-block" style="font-family:monospace;font-size:10.5px">
                                            {{ $report->barcode }}
                                        </small>
                                    @endif
                                </td>
                                <td>
                                    {{ $report->patient?->name ?? 'Removed patient' }}
                                    <small class="text-muted d-block">
                                        {{ $report->patient?->age ? $report->patient->age.'y' : '' }}
                                        {{ $report->patient?->bloodgroup }}
                                    </small>
                                </td>
                                <td>
                                    @if ($report->appointment_id)
                                        <span class="badge badge-info">OPD #{{ $report->appointment_id }}</span>
                                    @else
                                        <span class="text-muted" style="font-size:11px">Ward / walk-in</span>
                                    @endif
                                    <small class="text-muted d-block">by {{ $report->orderedBy?->name ?? '-' }}</small>
                                </td>
                                <td>
                                    @if ($report->priority === 'stat')
                                        <span class="badge badge-danger">STAT</span>
                                    @else
                                        <span class="badge badge-secondary">Routine</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $badge = ['pending' => 'warning', 'in_progress' => 'primary', 'reported' => 'success'][$report->status] ?? 'secondary';
                                    @endphp
                                    <span class="badge badge-{{ $badge }}">{{ str_replace('_', ' ', ucfirst($report->status)) }}</span>
                                    @if ($report->is_critical)
                                        <span class="badge badge-danger d-block mt-1">CRITICAL</span>
                                    @endif
                                    @if ($report->reported_at)
                                        <small class="text-muted d-block">{{ optional($report->reported_at)->format('d M, h:i A') }}</small>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if ($report->status === 'pending')
                                        <button class="btn btn-xs btn-outline-primary" wire:click="start({{ $report->id }})" title="Start">
                                            <i class="fas fa-play"></i>
                                        </button>
                                    @endif
                                    @if (! $report->barcode && in_array($report->status, ['pending', 'in_progress'], true))
                                        <button class="btn btn-xs btn-outline-info" wire:click="collectSample({{ $report->id }})" title="Collect sample">
                                            <i class="fas fa-barcode"></i>
                                        </button>
                                    @endif
                                    @if ($report->barcode)
                                        <a class="btn btn-xs btn-outline-dark" href="{{ route('admin_lab_barcode_print', ['report' => $report->id]) }}"
                                            target="_blank" title="Print slip">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    @endif
                                    @if (in_array($report->status, ['pending', 'in_progress'], true))
                                        <button class="btn btn-xs btn-outline-success" wire:click="openReport({{ $report->id }})" title="Enter result">
                                            <i class="fas fa-clipboard-check"></i>
                                        </button>
                                        <button class="btn btn-xs btn-outline-danger" wire:click="cancel({{ $report->id }})"
                                            onclick="return confirm('Cancel this order?')" title="Cancel">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    @endif
                                    @if ($report->status === 'reported')
                                        <small class="text-muted" style="font-size:11px">{{ $report->reportedBy?->name }}</small>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-3">No orders in this list.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                @if ($reports->hasPages())
                    <div class="mt-2">{{ $reports->links() }}</div>
                @endif
            </div>
        </div>
    </div>

    @if ($reportId)
        <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.45)">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header py-2">
                        <h5 class="modal-title"><i class="fas fa-clipboard-check text-info mr-1"></i> Report result</h5>
                        <button type="button" class="close" wire:click="closeReport"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Result values <span class="text-muted">(numbers, ranges - printed on the report)</span></label>
                            <textarea rows="3" class="form-control form-control-sm" wire:model="reportResult"
                                placeholder="Hb 13.5 g/dL, TLC 7200, ..."></textarea>
                        </div>
                        <div class="form-group mb-0">
                            <label>Findings / interpretation</label>
                            <textarea rows="3" class="form-control form-control-sm" wire:model="reportFindings"
                                placeholder="Within normal limits, ..."></textarea>
                            @error('reportFindings') <span class="text-danger" style="font-size:10.5px">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label>Result file <span class="text-muted">(PDF / JPG / PNG, max 8 MB)</span></label>
                            <input type="file" class="form-control-file form-control-sm" wire:model="resultFile"
                                accept=".pdf,.jpg,.jpeg,.png">
                            <div wire:loading wire:target="resultFile" class="text-muted" style="font-size:10.5px">Uploading...</div>
                            @error('resultFile') <span class="text-danger d-block" style="font-size:10.5px">{{ $message }}</span> @enderror
                        </div>
                        <div class="custom-control custom-checkbox mb-1">
                            <input type="checkbox" class="custom-control-input" id="isCritical" wire:model="isCritical">
                            <label class="custom-control-label" for="isCritical">
                                Critical result <span class="text-danger">(alert the doctors)</span>
                            </label>
                        </div>
                        @if ($isCritical)
                            <div class="form-group mb-0">
                                <label>Critical note</label>
                                <textarea rows="2" class="form-control form-control-sm" wire:model="criticalNote"
                                    placeholder="What the doctor must know..."></textarea>
                                @error('criticalNote') <span class="text-danger" style="font-size:10.5px">{{ $message }}</span> @enderror
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="closeReport">Cancel</button>
                        <button type="button" class="btn btn-sm btn-primary" wire:click="saveResult">
                            <i class="fas fa-save"></i> Save &amp; report
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
