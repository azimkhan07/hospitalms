<div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 style="font-size:22px;margin:0;color:#0b3c66">Error Monitor</h2>
            <p style="font-size:13px;color:#61748a;margin:2px 0 0">
                Every captured exception across all tenants, in one place.
            </p>
        </div>
        <button type="button" class="btn btn-outline-danger btn-sm" wire:click="clearResolved"
            wire:confirm="Delete all resolved errors permanently?">
            <i class="fas fa-broom"></i> Clear resolved
        </button>
    </div>

    @if (session('sanotice'))
        <div class="alert alert-success py-2" style="font-size:13px">{{ session('sanotice') }}</div>
    @endif

    <div class="row mb-3">
        <div class="col-md-4 mb-2">
            <div class="sa-stat">
                <div class="label">Open</div>
                <div class="value" style="color:#d9534f">{{ $counts['unresolved'] }}</div>
            </div>
        </div>
        <div class="col-md-4 mb-2">
            <div class="sa-stat">
                <div class="label">Critical open</div>
                <div class="value" style="color:#a94442">{{ $counts['critical'] }}</div>
            </div>
        </div>
        <div class="col-md-4 mb-2">
            <div class="sa-stat">
                <div class="label">Resolved</div>
                <div class="value" style="color:#5cb85c">{{ $counts['resolved'] }}</div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="row">
                <div class="col-md-6 mb-2">
                    <input type="text" class="form-control form-control-sm"
                        placeholder="Search message, class or URL..."
                        wire:model.live.debounce.300ms="search">
                </div>
                <div class="col-md-3 mb-2">
                    <select class="form-control form-control-sm" wire:model.live="level">
                        <option value="">All levels</option>
                        <option value="error">Error</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <select class="form-control form-control-sm" wire:model.live="status">
                        <option value="unresolved">Unresolved</option>
                        <option value="resolved">Resolved</option>
                        <option value="">All</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="list-group list-group-flush">
            @forelse ($logs as $log)
                <div class="list-group-item" wire:key="log-{{ $log->id }}">
                    <div class="d-flex justify-content-between align-items-start">
                        <div style="min-width:0">
                            <span
                                class="badge badge-{{ $log->level === 'critical' ? 'danger' : 'warning' }} mr-1">
                                {{ $log->level }}
                            </span>
                            @if ($log->resolved_at)
                                <span class="badge badge-success mr-1">resolved</span>
                            @endif
                            <strong style="font-size:13px">{{ $log->short_message }}</strong>
                            <div style="font-size:12px;color:#61748a">
                                <i class="fas fa-code"></i>
                                {{ class_basename($log->exception_class) }}
                                &middot; {{ $log->method }} {{ $log->url }}
                            </div>
                            <div style="font-size:11px;color:#8a9aab">
                                {{ $log->tenant->name ?? 'Platform' }}
                                &middot; {{ $log->file }}:{{ $log->line }}
                                &middot; {{ $log->created_at?->format('d M Y H:i') }}
                            </div>
                        </div>
                        <div class="text-right" style="white-space:nowrap">
                            <button type="button" class="btn btn-xs btn-outline-secondary"
                                wire:click="toggle({{ $log->id }})">
                                <i class="fas fa-{{ $expanded === $log->id ? 'chevron-up' : 'chevron-down' }}"></i>
                            </button>
                            @if ($log->resolved_at)
                                <button type="button" class="btn btn-xs btn-outline-warning"
                                    wire:click="unresolve({{ $log->id }})">Reopen</button>
                            @else
                                <button type="button" class="btn btn-xs btn-outline-success"
                                    wire:click="resolve({{ $log->id }})">Resolve</button>
                            @endif
                            <button type="button" class="btn btn-xs btn-outline-danger"
                                wire:click="delete({{ $log->id }})"
                                wire:confirm="Delete this error record?">Delete</button>
                        </div>
                    </div>

                    @if ($expanded === $log->id)
                        <div class="mt-2">
                            <pre style="background:#0b3c66;color:#dbe9f7;padding:12px;border-radius:6px;font-size:11px;max-height:320px;overflow:auto">{{ $log->stack }}</pre>
                            @if (! empty($log->context))
                                <div style="font-size:12px;color:#61748a">
                                    <strong>Context</strong>
                                    <pre style="background:#f4f8fc;padding:10px;border-radius:6px;font-size:11px;max-height:180px;overflow:auto">{{ json_encode($log->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <div class="list-group-item text-center text-muted py-4">
                    <i class="fas fa-shield-halved"></i> No errors recorded. All good.
                </div>
            @endforelse
        </div>
        @if ($logs->hasPages())
            <div class="card-footer bg-white py-2">{{ $logs->links() }}</div>
        @endif
    </div>
</div>
