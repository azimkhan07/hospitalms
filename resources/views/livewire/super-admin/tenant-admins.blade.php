<div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 style="font-size:17px;margin:0;color:#0b3c66">Tenant Admins</h2>
            <p style="font-size:13px;color:#61748a;margin:2px 0 0">
                Manage tenant administrators. Each admin belongs to exactly one hospital/clinic.
            </p>
        </div>
        <button type="button" class="btn btn-primary btn-sm" wire:click="openCreate">
            <i class="fas fa-plus"></i> New admin
        </button>
    </div>

    @if (session('sanotice'))
        <div class="alert alert-success py-2" style="font-size:13px">{{ session('sanotice') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="row">
                <div class="col-md-6 mb-2">
                    <input type="text" class="form-control form-control-sm"
                        placeholder="Search by name, email, or tenant..."
                        wire:model.live.debounce.300ms="search">
                </div>
                <div class="col-md-3 mb-2">
                    <select class="form-control form-control-sm" wire:model.live="tenantId">
                        <option value="">All hospitals/clinics</option>
                        @foreach ($tenants as $t)
                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <select class="form-control form-control-sm" wire:model.live="status">
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" style="font-size:13px">
                    <thead class="bg-light">
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Hospital / Clinic</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Last login</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($admins as $admin)
                            <tr>
                                <td>{{ $admin->name }}</td>
                                <td>{{ $admin->email }}</td>
                                <td>{{ $admin->tenant?->name ?? '-' }}</td>
                                <td><span class="badge badge-primary">{{ $admin->roleSlug() ?? 'admin' }}</span></td>
                                <td>
                                    @if ($admin->is_active)
                                        <span class="badge badge-success">Active</span>
                                    @else
                                        <span class="badge badge-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td>{{ $admin->created_at?->format('Y-m-d') }}</td>
                                <td>{{ $admin->last_login_at?->diffForHumans() ?? '-' }}</td>
                                <td class="text-right">
                                    <button type="button" class="btn btn-sm btn-light"
                                        wire:click="openEdit({{ $admin->id }})">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-light"
                                        wire:confirm="Delete this admin?" wire:click="delete({{ $admin->id }})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-3 text-muted">No admins found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($admins->hasPages())
                <div class="px-3 py-2 border-top bg-light">
                    {{ $admins->links() }}
                </div>
            @endif
        </div>
    </div>

    @if ($showForm)
        <div class="modal show d-block" tabindex="-1" style="background:rgba(0,0,0,.25)">
            <div class="modal-dialog modal-md" style="max-width:720px">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" style="font-size:15px;color:#0b3c66">
                            {{ $adminId ? 'Edit tenant admin' : 'New tenant admin' }}
                        </h5>
                        <button type="button" class="close" wire:click="closeForm">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-12 form-group">
                                <label style="font-size:13px">Hospital / Clinic <span class="text-danger">*</span></label>
                                <select class="form-control @error('tenant_id') is-invalid @enderror" wire:model="tenant_id">
                                    <option value="">-- Select hospital/clinic --</option>
                                    @foreach ($tenantsList as $t)
                                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                                    @endforeach
                                </select>
                                @error('tenant_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <small class="text-muted">Only one hospital/clinic can be assigned (single selection).</small>
                            </div>
                            <div class="col-md-6 form-group">
                                <label style="font-size:13px">Full name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" wire:model="name">
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 form-group">
                                <label style="font-size:13px">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror" wire:model="email">
                                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6 form-group">
                                <label style="font-size:13px">Password <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('password') is-invalid @enderror" wire:model="password" placeholder="{{ $adminId ? 'Leave blank to keep current' : 'min 6 characters' }}">
                                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3 form-group">
                                <label style="font-size:13px">Status</label>
                                <select class="form-control" wire:model="is_active">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light btn-sm" wire:click="closeForm">Cancel</button>
                        <button type="button" class="btn btn-primary btn-sm" wire:click="save" wire:loading.attr="disabled">
                            <i class="fas fa-save"></i> {{ $adminId ? 'Update' : 'Create' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
