<div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 style="font-size:17px;margin:0;color:#0b3c66">Audit Log</h2>
            <p style="font-size:13px;color:#61748a;margin:2px 0 0">
                Every write to the core records across all tenants.
            </p>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-3 mb-2">
            <div class="sa-stat">
                <div class="label">Total entries</div>
                <div class="value" style="color:#0b3c66">{{ $counts['total'] }}</div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="sa-stat">
                <div class="label">Created</div>
                <div class="value" style="color:#5cb85c">{{ $counts['created'] }}</div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="sa-stat">
                <div class="label">Updated</div>
                <div class="value" style="color:#f0ad4e">{{ $counts['updated'] }}</div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="sa-stat">
                <div class="label">Deleted</div>
                <div class="value" style="color:#d9534f">{{ $counts['deleted'] }}</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row g-2 mb-3">
                <div class="col-md-4">
                    <input type="text" class="form-control form-control-sm" placeholder="Search summary, model, IP..."
                        wire:model.live.debounce.300ms="search">
                </div>
                <div class="col-md-2">
                    <select class="form-control form-control-sm" wire:model.live="action">
                        <option value="">All actions</option>
                        @foreach ($actions as $a)
                            <option value="{{ $a }}">{{ ucfirst($a) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-control form-control-sm" wire:model.live="tenantFilter">
                        <option value="">All tenants</option>
                        @foreach ($tenants as $t)
                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="clearFilters">Clear</button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0" style="font-size:13px">
                    <thead>
                        <tr style="color:#61748a;text-transform:uppercase;font-size:11px">
                            <th>When</th>
                            <th>Action</th>
                            <th>Record</th>
                            <th>Tenant</th>
                            <th>Actor</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td class="text-nowrap">{{ $log->created_at->format('d M Y H:i') }}</td>
                                <td>
                                    <span class="badge {{ $log->action === 'deleted' ? 'bg-danger' : ($log->action === 'updated' ? 'bg-warning text-dark' : 'bg-success') }}">
                                        {{ ucfirst($log->action) }}
                                    </span>
                                </td>
                                <td>
                                    <div>{{ $log->summary }}</div>
                                    <small class="text-muted">{{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}</small>
                                </td>
                                <td>{{ $log->tenant?->name ?? '<global>' }}</td>
                                <td>{{ $log->user?->name ?? 'system' }}</td>
                                <td>{{ $log->ip ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">
                                    No audit entries yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-2 d-flex justify-content-center">
                {{ $logs->links() }}
            </div>
        </div>
    </div>
</div>