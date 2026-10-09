<div>
    <div class="content">
        <div class="container">
            <div class="page-title">
                <h3 class="text-info">Day Book</h3>
            </div>

            <div>
                @if (session()->has('message'))
                    <div class="alert alert-success">
                        {{ session('message') }}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif
            </div>

            <div class="box box-primary">
                <div class="box-header with-border d-flex justify-content-between align-items-center">
                    <h3 class="box-title">Money movement · {{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}</h3>
                    <div class="form-inline">
                        <input type="date" class="form-control form-control-sm mr-2" wire:model.live="date">
                        <label class="mb-0 mr-2">
                            <input type="checkbox" wire:model.live="showAdvanced"> Ref
                        </label>
                    </div>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="small-box bg-green">
                                <div class="inner">
                                    <h3>{{ number_format($totals['receipts'], 2) }}</h3>
                                    <p>Receipts (bill payments)</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="small-box bg-yellow">
                                <div class="inner">
                                    <h3>+{{ number_format($totals['voucher_inflow'], 2) }} / -{{ number_format($totals['voucher_outflow'], 2) }}</h3>
                                    <p>Ledger vouchers in / out</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="small-box bg-blue">
                                <div class="inner">
                                    <h3>{{ number_format($totals['net'], 2) }}</h3>
                                    <p>Net movement</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive p-0">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Source</th>
                                    <th>Particulars</th>
                                    @if ($showAdvanced)
                                        <th>Ref</th>
                                    @endif
                                    <th>Type</th>
                                    <th class="text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($entries as $entry)
                                    <tr>
                                        <td>{{ $entry['occurred_at']?->format('d M Y H:i') }}</td>
                                        <td>
                                            <span class="label {{ $entry['source'] === 'receipt' ? 'label-info' : 'label-default' }}">
                                                {{ ucfirst($entry['source']) }}
                                            </span>
                                        </td>
                                        <td>
                                            {{ $entry['title'] }}
                                            @if ($entry['note'])
                                                <br><small class="text-muted">{{ $entry['note'] }}</small>
                                            @endif
                                        </td>
                                        @if ($showAdvanced)
                                            <td>{{ $entry['ref'] }}</td>
                                        @endif
                                        <td>
                                            <span class="label {{ $entry['type'] === 'in' ? 'label-success' : 'label-danger' }}">
                                                {{ $entry['type'] === 'in' ? 'In' : 'Out' }}
                                            </span>
                                        </td>
                                        <td class="text-right">{{ number_format($entry['amount'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $showAdvanced ? 6 : 5 }}" class="text-muted text-center">
                                            No movement recorded on this date.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
