<div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 style="font-size:17px;margin:0;color:#0b3c66">Hospitals &amp; Clinics</h2>
            <p style="font-size:13px;color:#61748a;margin:2px 0 0">
                Create a tenant, choose its mode, and hand over its first admin.
            </p>
        </div>
        <button type="button" class="btn btn-primary btn-sm" wire:click="openCreate">
            <i class="fas fa-plus"></i> New tenant
        </button>
        <a href="{{ route('superadmin.tenants.export', [
            'search' => $search,
            'status' => $status,
            'mode' => $mode,
            'type' => $type,
            'unassigned' => $unassignedOnly ? 1 : 0,
        ]) }}" class="btn btn-outline-primary btn-sm ml-1" title="Export visible facilities as CSV">
            <i class="fas fa-file-csv"></i> Export
        </a>
    </div>

    @if (session('sanotice'))
        <div class="alert alert-success py-2" style="font-size:13px">{{ session('sanotice') }}</div>
    @endif

    {{-- Global view (PLAN.md §9c.5): what the platform hosts, and what is still unbuilt --}}
    <div class="row mb-3">
        <div class="col-6 col-md-2 mb-2">
            <div class="card h-100"><div class="card-body py-2">
                <div style="font-size:11px;color:#61748a">Facilities</div>
                <div style="font-size:20px;color:#0b3c66">{{ $stats['total'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-2 mb-2">
            <div class="card h-100"><div class="card-body py-2">
                <div style="font-size:11px;color:#61748a">Hospitals</div>
                <div style="font-size:20px;color:#0b3c66">{{ $stats['hospitals'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-2 mb-2">
            <div class="card h-100"><div class="card-body py-2">
                <div style="font-size:11px;color:#61748a">Clinics</div>
                <div style="font-size:20px;color:#0b3c66">{{ $stats['clinics'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-2 mb-2">
            <a href="#" wire:click.prevent="$toggle('unassignedOnly')" style="text-decoration:none">
                <div class="card h-100"><div class="card-body py-2">
                    <div style="font-size:11px;color:#61748a">Needs an admin</div>
                    <div style="font-size:20px;color:{{ $stats['unassigned'] ? '#dc3545' : '#0b3c66' }}">
                        {{ $stats['unassigned'] }}
                    </div>
                </div></div>
            </a>
        </div>
        <div class="col-6 col-md-2 mb-2">
            <div class="card h-100"><div class="card-body py-2">
                <div style="font-size:11px;color:#61748a">Staff accounts</div>
                <div style="font-size:20px;color:#0b3c66">{{ $stats['staff'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-2 mb-2">
            <div class="card h-100"><div class="card-body py-2">
                <div style="font-size:11px;color:#61748a">Facility types</div>
                <div style="font-size:20px;color:#0b3c66">{{ $stats['byType']->count() }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-2 mb-2">
            <div class="card h-100"><div class="card-body py-2">
                <div style="font-size:11px;color:#61748a">Beds</div>
                <div style="font-size:20px;color:#0b3c66">{{ $stats['beds'] }}</div>
                <div style="font-size:11px;color:#61748a">
                    {{ $stats['beds'] ? round(($stats['bedsAlloted'] / $stats['beds']) * 100) : 0 }}% occupied
                </div>
            </div></div>
        </div>
    </div>

    @if ($stats['byType']->isNotEmpty())
        <div class="card mb-3">
            <div class="card-body py-2" style="font-size:12px">
                <strong style="color:#0b3c66">By type:</strong>
                @foreach ($stats['byType'] as $typeName => $total)
                    <span class="badge badge-light mr-1">{{ $typeName }} · {{ $total }}</span>
                @endforeach
            </div>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="row">
                <div class="col-md-4 mb-2">
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
                <div class="col-md-2 mb-2">
                    <select class="form-control form-control-sm" wire:model.live="type">
                        <option value="">All types</option>
                        @foreach ($clinicTypes as $ct)
                            <option value="{{ $ct->id }}">{{ $ct->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-2 d-flex align-items-center">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="unassigned-only"
                            wire:model.live="unassignedOnly">
                        <label class="form-check-label" for="unassigned-only"
                            style="font-size:13px">Needs an admin</label>
                    </div>
                    <button type="button" class="btn btn-link btn-sm p-0 ml-2"
                        style="font-size:12px" wire:click="resetFilters">Clear</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Facility</th>
                        <th>Type</th>
                        <th>Roles it needs</th>
                        <th>Staff</th>
                        <th>Beds</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tenants as $tenant)
                        <tr wire:key="tenant-{{ $tenant->id }}">
                            <td>
                                <strong>{{ $tenant->name }}</strong>
                                @if ($tenant->admin_count == 0)
                                    <span class="badge badge-danger ml-1">NEW</span>
                                    <div style="font-size:11px;color:#dc3545">No admin assigned yet</div>
                                @endif
                                <div style="font-size:12px;color:#61748a">{{ $tenant->slug }}</div>
                            </td>
                            <td>
                                <span class="badge badge-info">{{ $tenant->typeLabel() }}</span>
                                <div style="font-size:11px;color:#61748a">{{ $tenant->modeLabel() }}</div>
                            </td>
                            <td style="min-width:220px">
                                @if ($tenant->requirements->isEmpty())
                                    <span class="text-muted" style="font-size:12px">Not set — open edit</span>
                                @else
                                    <div class="d-flex flex-wrap">
                                        @foreach ($tenant->requirements as $req)
                                            @php $have = $tenant->role_counts[$req->role->slug] ?? 0; @endphp
                                            <span class="badge mr-1 mb-1"
                                                style="font-size:10px;background:{{ $have > 0 ? '#e8f4ff' : '#fff4e5' }};color:{{ $have > 0 ? '#0b3c66' : '#b35c00' }};border:1px solid {{ $have > 0 ? '#cfe4f7' : '#ffd9a8' }}">
                                                {{ hms_role_label_for_slug($req->role->slug) }}
                                                <strong>{{ $have }}</strong>
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td>
                                {{ $tenant->users_count }}
                                <div style="font-size:11px;color:#61748a">admins: {{ $tenant->admin_count }}</div>
                            </td>
                            <td>{{ $tenant->bed_count }}</td>
                            <td>
                                <span
                                    class="badge badge-{{ $tenant->status === 'active' ? 'success' : ($tenant->status === 'trial' ? 'warning' : 'secondary') }}">
                                    {{ $tenant->status }}
                                </span>
                            </td>
                            <td class="text-right" style="white-space:nowrap">
                                @if ($tenant->admin_count == 0)
                                    <button type="button" class="btn btn-xs btn-primary"
                                        wire:click="assignAdmin({{ $tenant->id }})" title="Assign an admin">
                                        <i class="fas fa-user-plus"></i> Assign admin
                                    </button>
                                @endif
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
