<div class="content">
    <div class="container">
        <div class="page-title">
            <h3 class="text-info">Medicine Expiry &amp; Stock</h3>
        </div>

        <div class="row">
            <div class="col-lg-3 col-sm-4 col-6">
                <div class="info-box">
                    <span class="info-box-icon bg-danger"><i class="fas fa-ban"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Expired</span>
                        <span class="info-box-number">{{ $counts['expired'] }}</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-sm-4 col-6">
                <div class="info-box">
                    <span class="info-box-icon bg-warning"><i class="fas fa-hourglass-half"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Expiring &le;30d</span>
                        <span class="info-box-number">{{ $counts['soon'] }}</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-sm-4 col-6">
                <div class="info-box">
                    <span class="info-box-icon bg-info"><i class="fas fa-box-open"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Out of stock</span>
                        <span class="info-box-number">{{ $counts['outofstock'] }}</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-sm-4 col-6">
                <div class="info-box">
                    <span class="info-box-icon bg-success"><i class="fas fa-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Healthy items</span>
                        <span class="info-box-number">{{ $counts['valid'] ?? 0 }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="box box-primary">
            <div class="box-header d-flex align-items-center justify-content-between flex-wrap">
                <h3 class="box-title"><i class="fas fa-exclamation-triangle text-danger mr-1"></i> Expired Medicines</h3>
                <input type="search" class="form-control form-control-sm" style="max-width:220px"
                    placeholder="Search medicine, batch, code..." wire:model.live.debounce.300ms="search">
            </div>

            <div class="box-body">
                <div class="mb-2">
                    <button type="button"
                        class="btn btn-xs {{ $filter === 'expired' ? 'btn-danger' : 'btn-outline-secondary' }} mr-1"
                        wire:click="$set('filter', 'expired')">Expired ({{ $counts['expired'] }})</button>
                    <button type="button"
                        class="btn btn-xs {{ $filter === 'soon' ? 'btn-warning' : 'btn-outline-secondary' }} mr-1"
                        wire:click="$set('filter', 'soon')">Expiring in 30 days ({{ $counts['soon'] }})</button>
                    <button type="button"
                        class="btn btn-xs {{ $filter === 'outofstock' ? 'btn-info' : 'btn-outline-secondary' }} mr-1"
                        wire:click="$set('filter', 'outofstock')">Out of stock ({{ $counts['outofstock'] }})</button>
                </div>

                <div class="text-info" wire:loading>Loading..</div>

                <table class="table table-sm table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th>Code</th>
                            <th>Batch</th>
                            <th>Manufacturer</th>
                            <th>Expiry</th>
                            <th>Status</th>
                            <th>Stock</th>
                            <th>Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($medicines as $m)
                            @php
                                $expired = $m->expiry_date && $m->expiry_date->isPast();
                                $soon = $m->expiry_date && ! $expired && $m->expiry_date->lte(today()->addDays(30));
                            @endphp
                            <tr>
                                <td>{{ $m->name ?? '(unnamed item #'.$m->id.')' }}</td>
                                <td>{{ $m->code ?? '-' }}</td>
                                <td>{{ $m->batch_no ?? '-' }}</td>
                                <td>{{ $m->manufacturer ?? '-' }}</td>
                                <td>{{ $m->expiry_date?->format('d M Y') ?? 'Not set' }}</td>
                                <td>
                                    @if ($expired)
                                        <span class="badge badge-danger">Expired</span>
                                    @elseif ($soon)
                                        <span class="badge badge-warning">{{ $m->expiry_date->diffInDays(today()) }}d left</span>
                                    @elseif (is_null($m->stock) || $m->stock <= 0)
                                        <span class="badge badge-info">Out of stock</span>
                                    @else
                                        <span class="badge badge-success">Valid</span>
                                    @endif
                                </td>
                                <td>{{ $m->stock ?? $m->quantity ?? '-' }}</td>
                                <td>{{ $m->price ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-2">Nothing in this list.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                @if ($medicines->hasPages())
                    <div class="mt-2">{{ $medicines->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>