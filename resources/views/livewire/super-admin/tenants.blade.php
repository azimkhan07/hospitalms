<div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 style="font-size:22px;margin:0;color:#0b3c66">Hospitals &amp; Clinics</h2>
            <p style="font-size:13px;color:#61748a;margin:2px 0 0">
                Create a tenant, choose its mode, and hand over its first admin.
            </p>
        </div>
        <button type="button" class="btn btn-primary btn-sm" wire:click="openCreate">
            <i class="fas fa-plus"></i> New tenant
        </button>
    </div>

    @if (session('sanotice'))
        <div class="alert alert-success py-2" style="font-size:13px">{{ session('sanotice') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="row">
                <div class="col-md-5 mb-2">
                    <input type="text" class="form-control form-control-sm"
                        placeholder="Search name, slug, email or domain..."
                        wire:model.live.debounce.300ms="search">
                </div>
                <div class="col-md-2 mb-2">
                    <select class="form-control form-control-sm" wire:model.live="status">
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                        <option value="trial">Trial</option>
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <select class="form-control form-control-sm" wire:model.live="mode">
                        <option value="">All modes</option>
                        @foreach ($modes as $key => $cfg)
                            <option value="{{ $key }}">{{ $cfg['label'] ?? ucfirst($key) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Tenant</th>
                        <th>Mode</th>
                        <th>Contact</th>
                        <th>Domain</th>
                        <th>Users</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tenants as $tenant)
                        <tr wire:key="tenant-{{ $tenant->id }}">
                            <td>
                                <strong>{{ $tenant->name }}</strong>
                                <div style="font-size:12px;color:#61748a">{{ $tenant->slug }}</div>
                            </td>
                            <td><span class="badge badge-info">{{ $tenant->mode }}</span></td>
                            <td style="font-size:12px">
                                {{ $tenant->email ?: '—' }}
                                <div style="color:#61748a">{{ $tenant->phone ?: '' }}</div>
                            </td>
                            <td style="font-size:12px">{{ $tenant->domain ?: '—' }}</td>
                            <td>{{ $tenant->users_count }}</td>
                            <td>
                                <span
                                    class="badge badge-{{ $tenant->status === 'active' ? 'success' : ($tenant->status === 'trial' ? 'warning' : 'secondary') }}">
                                    {{ $tenant->status }}
                                </span>
                            </td>
                            <td class="text-right" style="white-space:nowrap">
                                <button type="button" class="btn btn-xs btn-outline-primary"
                                    wire:click="edit({{ $tenant->id }})" title="Edit">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button" class="btn btn-xs btn-outline-secondary"
                                    wire:click="toggleStatus({{ $tenant->id }})"
                                    title="{{ $tenant->status === 'active' ? 'Suspend' : 'Activate' }}">
                                    <i class="fas fa-{{ $tenant->status === 'active' ? 'ban' : 'circle-check' }}"></i>
                                </button>
                                <button type="button" class="btn btn-xs btn-outline-danger"
                                    wire:click="delete({{ $tenant->id }})"
                                    wire:confirm="Archive this tenant? Its admin will lose access." title="Archive">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                No tenants found. Create the first one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($tenants->hasPages())
            <div class="card-footer bg-white py-2">{{ $tenants->links() }}</div>
        @endif
    </div>

    @livewire('super-admin.tenant-form')
</div>
