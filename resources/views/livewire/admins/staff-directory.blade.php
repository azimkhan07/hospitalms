<div class="box box-primary">
    <div class="box-header d-flex align-items-center justify-content-between flex-wrap">
        <h3 class="box-title"><i class="fas fa-user-friends text-info mr-1"></i> Staff Directory</h3>
        <input type="search" class="form-control form-control-sm" style="max-width:220px"
            placeholder="Search name, email, department..." wire:model.live.debounce.300ms="search">
    </div>

    <div class="box-body">
        @if (session()->has('error'))
            <div class="alert alert-danger py-1 px-2">{{ session('error') }}</div>
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
                            <button type="button"
                                class="btn btn-xs {{ $user->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}"
                                wire:click="toggleActive({{ $user->id }})"
                                title="{{ $user->is_active ? 'Disable account' : 'Enable account' }}">
                                <i class="fas {{ $user->is_active ? 'fa-ban' : 'fa-check' }}"></i>
                            </button>
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