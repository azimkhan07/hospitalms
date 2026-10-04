<div class="box box-primary">
    <div class="box-header d-flex align-items-center justify-content-between flex-wrap">
        <h3 class="box-title"><i class="fas fa-user-friends text-info mr-1"></i> Staff Directory</h3>
        <div class="d-flex align-items-center">
            <input type="search" class="form-control form-control-sm mr-2" style="max-width:220px"
                placeholder="Search name, email, department..." wire:model.live.debounce.300ms="search">
            @if ($canManage)
                <button type="button" class="btn btn-xs btn-primary" wire:click="createStaff">
                    <i class="fas fa-user-plus"></i> Add Staff
                </button>
            @endif
        </div>
    </div>

    <div class="box-body">
        @if (session()->has('message'))
            <div class="alert alert-success py-1 px-2">{{ session('message') }}</div>
        @endif
        @if (session()->has('error'))
            <div class="alert alert-danger py-1 px-2">{{ session('error') }}</div>
        @endif

        @if ($showForm)
            @php $editing = $editingId !== null; @endphp
            <form class="card card-outline card-primary mb-3" wire:submit.prevent="saveStaff">
                <div class="card-header py-2">
                    <strong>{{ $editing ? 'Edit staff account' : 'New staff account' }}</strong>
                </div>
                <div class="card-body py-3">
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label style="font-size:12px">Name</label>
                            <input type="text" name="name" wire:model.lazy="name" class="form-control form-control-sm"
                                required>
                            @error('name')
                                <span class="text-danger text-xs">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group col-md-4">
                            <label style="font-size:12px">Email</label>
                            <input type="email" name="email" wire:model.lazy="email"
                                class="form-control form-control-sm" required>
                            @error('email')
                                <span class="text-danger text-xs">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group col-md-4">
                            <label style="font-size:12px">Phone</label>
                            <input type="text" name="phone" wire:model.lazy="phone"
                                class="form-control form-control-sm">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label style="font-size:12px">Role</label>
                            <select name="newRole" wire:model.lazy="newRole" class="form-control form-control-sm"
                                required>
                                <option value="">Choose a role</option>
                                @foreach ($roles as $r)
                                    <option value="{{ $r->slug }}">{{ $r->name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Only roles this facility's mode allows.</small>
                            @error('newRole')
                                <span class="text-danger text-xs">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group col-md-4">
                            <label style="font-size:12px">Designation</label>
                            <input type="text" name="designation" wire:model.lazy="designation"
                                class="form-control form-control-sm" placeholder="Senior Consultant">
                        </div>
                        <div class="form-group col-md-4">
                            <label style="font-size:12px">Department</label>
                            <input type="text" name="department" wire:model.lazy="department"
                                class="form-control form-control-sm" placeholder="Cardiology">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label style="font-size:12px">Password {{ $editing ? '(leave blank to keep)' : '' }}</label>
                            <input type="password" name="password" wire:model.lazy="password"
                                class="form-control form-control-sm" @if (! $editing) required @endif>
                            @error('password')
                                <span class="text-danger text-xs">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group col-md-4">
                            <label style="font-size:12px">Confirm password</label>
                            <input type="password" name="password_confirmation"
                                wire:model.lazy="password_confirmation" class="form-control form-control-sm"
                                @if (! $editing) required @endif>
                        </div>
                    </div>
                </div>
                <div class="card-footer py-2">
                    <button type="submit" class="btn btn-sm btn-primary">
                        {{ $editing ? 'Save changes' : 'Create account' }}
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" wire:click="cancelForm">Cancel</button>
                </div>
            </form>
        @endif

        <div class="mb-2">
            <button type="button"
                class="btn btn-xs {{ $role === '' ? 'btn-primary' : 'btn-outline-secondary' }} mr-1"
                wire:click="$set('role', '')">All</button>
            @foreach ($roles as $r)
                <button type="button"
                    class="btn btn-xs {{ $role === $r->slug ? 'btn-primary' : 'btn-outline-secondary' }} mr-1"
                    wire:click="$set('role', '{{ $r->slug }}')">
                    {{ $r->name }} <span class="badge badge-light">{{ $r->users_count }}</span>
                </button>
            @endforeach
        </div>

        <div class="text-info" wire:loading>Loading..</div>

        <table class="table table-sm table-bordered mb-0">
            <thead>
                <tr>
                    <th style="width:44px"></th>
                    <th>Name</th>
                    <th>Role</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Designation</th>
                    <th>Department</th>
                    <th>Last Login</th>
                    <th style="width:70px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr class="{{ $user->is_active ? '' : 'table-light text-muted' }}">
                        <td>
                            <img src="{{ storage_url(null, 'employee-placeholder.jpg') }}"
                                alt="{{ $user->name }}" class="rounded-circle" width="28" height="28">
                        </td>
                        <td>
                            {{ $user->name }}
                            @if ($user->isSuperAdmin())
                                <span class="badge badge-info badge-sm">Super Admin</span>
                            @endif
                        </td>
                        <td>{{ $user->role?->name ?? '-' }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->phone ?? '-' }}</td>
                        <td>{{ $user->designation ?? '-' }}</td>
                        <td>{{ $user->department ?? '-' }}</td>
                        <td>{{ $user->last_login_at?->format('d M Y H:i') ?? 'Never' }}</td>
                        <td class="text-right">
                            @if ($canManage)
                                <button type="button" class="btn btn-xs btn-outline-info"
                                    wire:click="editStaff({{ $user->id }})" title="Edit">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button"
                                    class="btn btn-xs {{ $user->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}"
                                    wire:click="toggleActive({{ $user->id }})"
                                    title="{{ $user->is_active ? 'Disable account' : 'Enable account' }}">
                                    <i class="fas {{ $user->is_active ? 'fa-ban' : 'fa-check' }}"></i>
                                </button>
                                <button type="button" class="btn btn-xs btn-outline-danger"
                                    wire:click="deleteStaff({{ $user->id }})"
                                    onclick="return confirm('Remove this staff account?')"
                                    title="Remove">
                                    <i class="fas fa-trash"></i>
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-2">No staff match this filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>