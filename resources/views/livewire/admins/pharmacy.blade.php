<div>
    <div class="content">
        <div class="container-fluid">
            <div class="row page-title">
                <div class="col">
                    <h3 class="text-info">Pharmacist Counter</h3>
                </div>
                <div class="col-auto">
                    <div class="btn-group">
                        <button class="btn @if ($filter === 'queue') btn-primary @else btn-default @endif" wire:click="$set('filter', 'queue')">Queue</button>
                        <button class="btn @if ($filter === 'dispensed') btn-primary @else btn-default @endif" wire:click="$set('filter', 'dispensed')">Dispensed</button>
                        <button class="btn @if ($filter === 'all') btn-primary @else btn-default @endif" wire:click="$set('filter', 'all')">All</button>
                    </div>
                </div>
            </div>

            @if (session()->has('success'))
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    {{ session('success') }}
                </div>
            @endif
            @if (session()->has('error'))
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    {{ session('error') }}
                </div>
            @endif

            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">E-prescriptions @if ($filter === 'queue')· awaiting dispensing @endif</h3>
                    <div class="box-tools pull-right">
                        <div class="input-group input-group-sm" style="width: 220px;">
                            <input type="text" class="form-control" wire:model.debounce.300ms="search" placeholder="Patient name...">
                        </div>
                    </div>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>RX #</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>When</th>
                                <th>Lines</th>
                                <th>State</th>
                                <th class="text-right">Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($prescriptions as $rx)
                                <tr>
                                    <td>#{{ $rx->id }}</td>
                                    <td class="text-bold">{{ $rx->patient?->name }}</td>
                                    <td>{{ $rx->doctor?->name }}</td>
                                    <td class="small">{{ $rx->issued_at?->format('d M Y') }}</td>
                                    <td>{{ $rx->items->count() }}</td>
                                    <td>
                                        @isset($rx->dispensed_at)
                                            <span class="badge @if ($rx->payment_status === 'paid') bg-success @else bg-info @endif">
                                                dispensed @if ($rx->payment_status === 'paid')· paid @endif
                                            </span>
                                        @else
                                            <span class="badge bg-warning">in queue</span>
                                        @endisset
                                    </td>
                                    <td class="text-right">
                                        @php
                                            $total = $rx->items->sum(fn ($item) => $item->drug ? (float) ($item->drug->mrp ?? $item->drug->price) * max(1, $item->dispensed_qty) : 0);
                                        @endphp
                                        {{ number_format($total, 2) }}
                                    </td>
                                    <td class="text-right">
                                        @if (! $rx->isDispensed())
                                            <button class="btn btn-outline-primary btn-xs" wire:click="open({{ $rx->id }})"><i class="fas fa-check"></i> Dispense</button>
                                        @elseif ($rx->payment_status !== 'paid')
                                            <button class="btn btn-outline-success btn-xs" wire:click="open({{ $rx->id }})"><i class="fas fa-money-bill-wave"></i> Collect</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted">No prescriptions here.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    {{ $prescriptions->links() }}
                </div>
            </div>

            @if ($open)
                <div class="modal show d-block" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Dispense RX #{{ $open->id }} — {{ $open->patient?->name }}</h5>
                                <button type="button" class="close" wire:click="close"><span>&times;</span></button>
                            </div>
                            <div class="modal-body table-responsive p-0">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Medicine</th>
                                            <th>Dose / frequency</th>
                                            <th class="text-center">In stock</th>
                                            <th>Next batch to expire</th>
                                            <th class="text-right">Unit price</th>
                                            <th class="text-center">State</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($open->items as $item)
                                            @php
$batches = $item->drug ? $pharmacy->availability($item->drug) : collect();
                $available = $pharmacy->balance($batches);
                $next = $batches->first();
                $unit = $item->drug ? (float) ($item->drug->mrp ?? $item->drug->price) : 0;
                                            @endphp
                                            <tr>
                                                <td class="text-bold">{{ $item->medicine }}</td>
                                                <td class="small">
                                                    {{ $item->dosage }} {{ $item->frequency }}
                                                    <div class="text-muted">{{ $item->duration }}</div>
                                                </td>
                                                <td class="text-center">
                                                    @if ($item->medicine_id && $available <= 0)
                                                        <span class="badge bg-danger">out of stock</span>
                                                    @else
                                                        {{ $item->medicine_id ? $available : '—' }}
                                                    @endif
                                                </td>
                                                <td class="small">
                                                    @if ($next)
                                                        {{ $next->batch_no }} · {{ $next->expiry_date?->format('d M Y') ?? 'no expiry' }}
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td class="text-right">{{ $item->medicine_id ? number_format($unit, 2) : '—' }}</td>
                                                <td class="text-center">
                                                    @if ($item->dispensed_qty > 0)
                                                        <span class="badge bg-success">dispensed {{ $item->dispensed_qty }}</span>
                                                    @elseif (! $item->medicine_id)
                                                        <span class="badge bg-default">not in master</span>
                                                    @elseif ($available <= 0)
                                                        <span class="badge bg-danger">blocked</span>
                                                    @else
                                                        <span class="badge bg-warning">awaiting</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="text-center text-muted">No items on this prescription.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="modal-footer">
                                <button class="btn btn-secondary" wire:click="close">Close</button>
                                @if (! $open->isDispensed())
                                    <button class="btn btn-warning" wire:click="dispense" wire:target="dispense" wire:loading.attr="disabled">Dispense (deduct stock)</button>
                                @elseif ($open->payment_status !== 'paid')
                                    <button class="btn btn-success" wire:click="markPaid('cash')">Payment done</button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-backdrop show"></div>
            @endif
        </div>
    </div>
</div>