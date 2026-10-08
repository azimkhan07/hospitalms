<div>
    <div class="card">
        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
            <div>
                <h2 style="font-size:17px;margin:0;color:#0b3c66">Platform facilities</h2>
                <p style="font-size:13px;color:#61748a;margin:2px 0 0">
                    Every clinic and hospital this platform hosts. Read-only — assignment happens
                    on the platform panel.
                </p>
            </div>
        </div>
        <div class="card-body py-2">
            <div class="row">
                <div class="col-md-4 mb-2">
                    <input type="text" class="form-control form-control-sm"
                        placeholder="Search name, slug or email..."
                        wire:model.live.debounce.300ms="search">
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
                <div class="col-md-4 mb-2 d-flex align-items-center">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="fac-unassigned-only"
                            wire:model.live="unassignedOnly">
                        <label class="form-check-label" for="fac-unassigned-only"
                            style="font-size:13px">Needs an admin</label>
                    </div>
                    <button type="button" class="btn btn-link btn-sm p-0 ml-2"
                        style="font-size:12px" wire:click="resetFilters">Clear</button>
                </div>
            </div>
        </div>
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
                    </tr>
                </thead>
                <tbody>
                    @forelse ($facilities as $facility)
                        <tr wire:key="facility-{{ $facility->id }}">
                            <td>
                                <strong>{{ $facility->name }}</strong>
                                @if (! $facility->has_admin)
                                    <span class="badge badge-danger ml-1">NEW</span>
                                    <div style="font-size:11px;color:#dc3545">No admin assigned yet</div>
                                @endif
                                <div style="font-size:12px;color:#61748a">{{ $facility->slug }}</div>
                            </td>
                            <td>
                                <span class="badge badge-info">{{ $facility->typeLabel() }}</span>
                                <div style="font-size:11px;color:#61748a">{{ $facility->modeLabel() }}</div>
                            </td>
                            <td style="min-width:220px">
                                @if ($facility->requirements->isEmpty())
                                    <span class="text-muted" style="font-size:12px">Not set</span>
                                @else
                                    <div class="d-flex flex-wrap">
                                        @foreach ($facility->requirements as $req)
                                            @php $have = $facility->role_counts[$req->role->slug] ?? 0; @endphp
                                            <span class="badge mr-1 mb-1"
                                                style="font-size:10px;background:{{ $have > 0 ? '#e8f4ff' : '#fff4e5' }};color:{{ $have > 0 ? '#0b3c66' : '#b35c00' }};border:1px solid {{ $have > 0 ? '#cfe4f7' : '#ffd9a8' }}">
                                                {{ hms_role_label_for_slug($req->role->slug) }}
                                                <strong>{{ $have }}</strong>
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td>{{ $facility->users_count }}</td>
                            <td>{{ $facility->bed_count }}</td>
                            <td>
                                <span
                                    class="badge badge-{{ $facility->status === 'active' ? 'success' : ($facility->status === 'trial' ? 'warning' : 'secondary') }}">
                                    {{ $facility->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                No facilities match these filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($facilities->hasPages())
            <div class="card-footer bg-white py-2">{{ $facilities->links() }}</div>
        @endif
    </div>
</div>