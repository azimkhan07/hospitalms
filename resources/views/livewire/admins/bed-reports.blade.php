<div class="box box-primary">
    <div class="box-header d-flex align-items-center justify-content-between flex-wrap">
        <h3 class="box-title"><i class="fas fa-clipboard-list text-info mr-1"></i> ICU / Bed Reports</h3>
        <div class="d-flex align-items-center no-print">
            @if ($bed || $room)
                <input type="search" class="form-control form-control-sm mr-2" style="max-width:180px"
                    placeholder="Search findings..." wire:model.live.debounce.300ms="search">
                <button type="button" class="btn btn-xs btn-default mr-2" onclick="window.print()">
                    <i class="fas fa-print"></i> Print this sheet
                </button>
                <button type="button" class="btn btn-xs btn-default" wire:click="clearSelection">Clear</button>
            @endif
        </div>
    </div>

    <div class="box-body">
        @if (session()->has('message'))
            <div class="alert alert-success py-1 px-2 no-print">{{ session('message') }}</div>
        @endif

        {{-- Live bed / room map: pick where you want to look. --}}
        <div class="row no-print">
            <div class="col-md-6">
                <div class="form-group">
                    <label style="font-size:12px"><strong>Beds</strong></label>
                    <div>
                        @forelse ($beds as $b)
                            <button type="button"
                                class="btn btn-xs {{ $bed && $bed->id === $b->id ? 'btn-primary' : 'btn-default' }} mr-1 mb-1"
                                wire:click="pickBed('{{ $b->id }}')">
                                Bed {{ $b->bed_number }}
                            </button>
                        @empty
                            <span class="text-muted" style="font-size:12px">No beds configured.</span>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label style="font-size:12px"><strong>Rooms</strong></label>
                    <div>
                        @forelse ($rooms as $r)
                            <button type="button"
                                class="btn btn-xs {{ $room && $room->id === $r->id ? 'btn-primary' : 'btn-default' }} mr-1 mb-1"
                                wire:click="pickRoom('{{ $r->id }}')">
                                Room {{ $r->name }}
                            </button>
                        @empty
                            <span class="text-muted" style="font-size:12px">No rooms configured.</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        @if (! $bed && ! $room)
            <div class="text-center text-muted py-4" style="font-size:13px">
                Pick a bed or a room above to see everything recorded against it.
            </div>
        @else
            {{-- Printable header: one sheet per bed or room. --}}
            <div class="row">
                <div class="col-md-6">
                    <h4 style="font-size:16px; margin:0">
                        {{ hms_tenant_brand()['name'] ?? config('app.name') }}
                    </h4>
                    <div class="text-muted" style="font-size:12px">
                        {{ $bed ? 'BED '.$bed->bed_number : 'ROOM '.$room->name }}
                        &middot; {{ now()->format('d M Y H:i') }}
                    </div>
                </div>
                <div class="col-md-6 text-right">
                    <div style="font-size:26px; font-weight:700;">
                        {{ number_format((float) $total, 2) }}
                    </div>
                    <div class="text-muted" style="font-size:12px">payable on {{ $reports->count() }} investigations</div>
                </div>
            </div>

            <hr style="margin:8px 0;">

            <div class="row">
                <div class="col-md-7">
                    <h5 style="font-size:13px; margin-bottom:4px;"><strong>Investigations</strong></h5>
                    <table class="table table-bordered table-sm mb-0" style="font-size:12px">
                        <thead>
                            <tr>
                                <th>Test</th>
                                <th>Units</th>
                                <th>Urgent</th>
                                <th>How it was worked out</th>
                                <th class="text-right">Charge</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($reports as $report)
                                <tr>
                                    <td>
                                        {{ $report->test?->name ?? 'Removed test' }}
                                        @if ($report->machine)
                                            <div class="text-muted" style="font-size:11px">{{ $report->machine->name }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $report->units }}</td>
                                    <td>{{ $report->is_urgent ? 'Yes' : 'No' }}</td>
                                    <td style="font-size:11px">{{ $report->formula ?: '-' }}</td>
                                    <td class="text-right">{{ number_format((float) $report->charge, 2) }}</td>
                                </tr>
                                @if ($report->findings)
                                    <tr>
                                        <td colspan="5" style="font-size:11px; background:#fbfbfb">
                                            <strong>Findings:</strong> {{ $report->findings }}
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">Nothing recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-right">Total</th>
                                <th class="text-right">{{ number_format((float) $total, 2) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="col-md-5">
                    <h5 style="font-size:13px; margin-bottom:4px;"><strong>Machines here</strong></h5>
                    <table class="table table-bordered table-sm mb-0" style="font-size:12px">
                        <tbody>
                            @forelse ($machines as $machine)
                                <tr>
                                    <td>
                                        {{ $machine->name }}
                                        @if ($machine->modality)
                                            <div class="text-muted" style="font-size:11px">{{ $machine->modality }}</div>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <span class="label label-{{ $machine->status === 'working' ? 'success' : 'warning' }}">
                                            {{ ucfirst(str_replace('_', ' ', $machine->status)) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="text-center text-muted">No machine attached here.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    @if ($canAddMachine)
                        <div class="mt-2 no-print">
                            @if ($showMachineForm)
                                <form class="card card-outline card-primary" wire:submit.prevent="createMachine">
                                    <div class="card-body py-2">
                                        <div class="form-group">
                                            <label style="font-size:12px">Machine name *</label>
                                            <input type="text" class="form-control form-control-sm @error('machineName') is-invalid @enderror"
                                                wire:model="machineName" placeholder="e.g. Portable Ventilator">
                                            @error('machineName') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="form-group">
                                            <label style="font-size:12px">Modality</label>
                                            <input type="text" class="form-control form-control-sm"
                                                wire:model="machineModality">
                                        </div>
                                        <div class="form-group">
                                            <label style="font-size:12px">Location</label>
                                            <input type="text" class="form-control form-control-sm"
                                                wire:model="machineLocation">
                                        </div>
{{-- The bed / room comes from the current selection, not from
                                             here: wire:model cannot sync a hidden field. --}}
                                        <div class="text-danger text-xs">
                                            Record this machine against
                                            {{ $bed ? 'bed '.$bed->bed_number : ($room ? 'room '.$room->name : 'nothing -- pick a bed or room first') }}.
                                        </div>
                                        <div class="d-flex">
                                            <button type="submit" class="btn btn-xs btn-primary mr-2">Record it</button>
                                            <button type="button" class="btn btn-xs btn-default"
                                                wire:click="$set('showMachineForm', false)">Cancel</button>
                                        </div>
                                    </div>
                                </form>
                            @else
                                <button type="button" class="btn btn-xs btn-default" wire:click="$set('showMachineForm', true)">
                                    <i class="fas fa-plus"></i> Add a machine in this ward
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>

