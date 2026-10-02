<div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 style="font-size:22px;margin:0;color:#0b3c66">Platform Overview</h2>
            <p style="font-size:13px;color:#61748a;margin:2px 0 0">
                Every hospital and clinic you run lives here.
            </p>
        </div>
        <a href="{{ route('superadmin.tenants') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Manage tenants
        </a>
    </div>

    <div class="row">
        <div class="col-lg-3 col-sm-6 mb-3">
            <div class="hms-sa-stat">
                <div class="label">Total tenants</div>
                <div class="value">{{ $stats['tenants'] }}</div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6 mb-3">
            <div class="hms-sa-stat">
                <div class="label">Active</div>
                <div class="value" style="color:#0f7fd4">{{ $stats['active'] }}</div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6 mb-3">
            <div class="hms-sa-stat">
                <div class="label">Hospitals</div>
                <div class="value">{{ $stats['hospitals'] }}</div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6 mb-3">
            <div class="hms-sa-stat">
                <div class="label">Clinics</div>
                <div class="value">{{ $stats['clinics'] }}</div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6 mb-3">
            <div class="hms-sa-stat">
                <div class="label">Tenant admins</div>
                <div class="value">{{ $stats['admins'] }}</div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6 mb-3">
            <div class="hms-sa-stat">
                <div class="label">Open errors</div>
                <div class="value" style="color:#d9534f">{{ $stats['unresolved'] }}</div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6 mb-3">
            <div class="hms-sa-stat">
                <div class="label">Errors (24h)</div>
                <div class="value">{{ $stats['errors_24h'] }}</div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6 mb-3">
            <div class="hms-sa-stat">
                <div class="label">Mode engine</div>
                <div class="value" style="font-size:18px">{{ ucfirst(hms_institution_mode()) }}</div>
            </div>
        </div>
    </div>

    <div class="row mt-2">
        <div class="col-lg-7 mb-3">
            <div class="card">
                <div class="card-header bg-white">
                    <strong>Recently added tenants</strong>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Mode</th>
                                <th>Status</th>
                                <th>Users</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentTenants as $tenant)
                                <tr>
                                    <td>
                                        <a href="{{ route('superadmin.tenants') }}">
                                            {{ $tenant->name }}
                                        </a>
                                        <div style="font-size:12px;color:#61748a">{{ $tenant->slug }}</div>
                                    </td>
                                    <td><span class="badge badge-info">{{ $tenant->mode }}</span></td>
                                    <td>
                                        <span
                                            class="badge badge-{{ $tenant->status === 'active' ? 'success' : 'secondary' }}">
                                            {{ $tenant->status }}
                                        </span>
                                    </td>
                                    <td>{{ $tenant->users_count }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">No tenants yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5 mb-3">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between">
                    <strong>Latest errors</strong>
                    <a href="{{ route('superadmin.errors') }}" style="font-size:12px">View all</a>
                </div>
                <ul class="list-group list-group-flush">
                    @forelse ($recentErrors as $error)
                        <li class="list-group-item">
                            <div style="font-size:13px">{{ $error->short_message }}</div>
                            <div style="font-size:11px;color:#61748a">
                                {{ $error->tenant->name ?? 'Platform' }}
                                &middot; {{ $error->created_at?->diffForHumans() }}
                            </div>
                        </li>
                    @empty
                        <li class="list-group-item text-center text-muted py-3">No errors logged.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
