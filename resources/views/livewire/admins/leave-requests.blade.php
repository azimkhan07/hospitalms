<div class="box box-primary">
    <div class="box-header d-flex align-items-center">
        <ul class="nav nav-pills" style="font-size:11.5px">
            <li class="nav-item">
                <button type="button" class="nav-link py-1 px-2 {{ $tab === 'mine' ? 'active' : '' }}"
                    wire:click="setTab('mine')">My Requests</button>
            </li>
            @if ($canReview)
                <li class="nav-item">
                    <button type="button"
                        class="nav-link py-1 px-2 {{ $tab === 'review' ? 'active' : '' }}"
                        wire:click="setTab('review')">
                        Review Queue
                        @if ($queue->total() ?? 0)
                            <span class="badge badge-warning">{{ $queue->total() }}</span>
                        @endif
                    </button>
                </li>
                <li class="nav-item">
                    <button type="button"
                        class="nav-link py-1 px-2 {{ $tab === 'decided' ? 'active' : '' }}"
                        wire:click="setTab('decided')">Decided</button>
                </li>
            @endif
        </ul>
    </div>

    <div class="box-body">
        @if (session()->has('success'))
            <div class="alert alert-success py-1 px-2">{{ session('success') }}</div>
        @endif

        @if ($tab === 'mine')
            <div class="row">
                <div class="col-lg-4">
                    <form wire:submit.prevent="submitRequest" class="border rounded p-2">
                        <div class="form-group">
                            <label>Leave Type</label>
                            <select class="form-control form-control-sm" wire:model="type">
                                @foreach (['casual', 'sick', 'annual', 'maternity', 'unpaid'] as $t)
                                    <option value="{{ $t }}">{{ ucfirst($t) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label>From</label>
                                <input type="date" class="form-control form-control-sm" wire:model="fromDate">
                                @error('fromDate')
                                    <span class="text-danger" style="font-size:10.5px">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="form-group col-6">
                                <label>To</label>
                                <input type="date" class="form-control form-control-sm" wire:model="toDate">
                                @error('toDate')
                                    <span class="text-danger" style="font-size:10.5px">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Reason</label>
                            <textarea rows="2" class="form-control form-control-sm"
                                placeholder="Why do you need leave?" wire:model="reason"></textarea>
                            @error('reason')
                                <span class="text-danger" style="font-size:10.5px">{{ $message }}</span>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary w-100">
                            <i class="fas fa-paper-plane"></i> Send for Approval
                        </button>
                    </form>
                </div>

                <div class="col-lg-8">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Reviewed By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($mine as $leave)
                                <tr>
                                    <td>{{ ucfirst($leave->type) }}</td>
                                    <td>{{ $leave->from_date->format('d M Y') }}</td>
                                    <td>{{ $leave->to_date->format('d M Y') }}</td>
                                    <td style="max-width:220px">{{ \Illuminate\Support\Str::limit($leave->reason ?? '-', 40) }}</td>
                                    <td>
                                        <span class="badge badge-sm
                                            @if ($leave->status === 'approved') badge-success
                                            @elseif ($leave->status === 'rejected') badge-danger
                                            @else badge-warning @endif">
                                            {{ ucfirst($leave->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        {{ $leave->reviewer?->name ?? '-' }}
                                        @if ($leave->review_note)
                                            <div class="text-muted" style="font-size:10.5px">
                                                {{ \Illuminate\Support\Str::limit($leave->review_note, 34) }}
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-2">No leave requests yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @elseif ($tab === 'review')
            <div class="text-info" wire:loading>Loading..</div>
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Staff</th>
                        <th>Role</th>
                        <th>Type</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Reason</th>
                        <th style="width:120px">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($queue as $leave)
                        <tr>
                            <td>{{ $leave->user?->name ?? 'Removed user' }}</td>
                            <td>{{ $leave->user?->role?->name ?? '-' }}</td>
                            <td>{{ ucfirst($leave->type) }}</td>
                            <td>{{ $leave->from_date->format('d M Y') }}</td>
                            <td>{{ $leave->to_date->format('d M Y') }}</td>
                            <td style="max-width:260px">{{ \Illuminate\Support\Str::limit($leave->reason ?? '-', 60) }}</td>
                            <td>
                                <button type="button" class="btn btn-xs btn-outline-primary"
                                    wire:click="openReview({{ $leave->id }})">Review</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-2">Nothing pending.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if ($queue->hasPages())
                <div class="mt-2">{{ $queue->links() }}</div>
            @endif

            @if ($reviewingId)
                @php $current = $queue->firstWhere('id', $reviewingId) ?? \App\Models\LeaveRequest::with('user.role')->find($reviewingId); @endphp
                <div class="hms-picker-backdrop" wire:click.self="$set('reviewingId', null)">
                    <div class="hms-picker" style="max-width:400px">
                        <div class="hms-picker-head">
                            <strong>Leave Request &mdash; {{ $current?->user?->name }}</strong>
                            <button type="button" class="close" wire:click="$set('reviewingId', null)" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="p-3" style="font-size:12px">
                            <div><strong>{{ ucfirst($current?->type) }}</strong> leave</div>
                            <div class="text-muted">
                                {{ $current?->from_date?->format('d M Y') }} &rarr; {{ $current?->to_date?->format('d M Y') }}
                                ({{ $current?->from_date && $current?->to_date ? $current->from_date->diffInDays($current->to_date) + 1 : 0 }} days)
                            </div>
                            <div class="p-2 bg-light rounded mt-2">{{ $current?->reason ?: 'No reason given.' }}</div>
                            <div class="form-group mt-2">
                                <label>Note {{ $reviewNote === '' ? '' : '' }}</label>
                                <textarea rows="2" class="form-control form-control-sm"
                                    placeholder="Required when rejecting" wire:model="reviewNote"></textarea>
                                @error('reviewNote')
                                    <span class="text-danger" style="font-size:10.5px">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="d-flex">
                                <button type="button" class="btn btn-sm btn-success mr-2"
                                    wire:click="decide('approved')"><i class="fas fa-check"></i> Approve</button>
                                <button type="button" class="btn btn-sm btn-danger"
                                    wire:click="decide('rejected')"><i class="fas fa-times"></i> Reject</button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @else
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Staff</th>
                        <th>Type</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Status</th>
                        <th>Reviewed By</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($decided as $leave)
                        <tr>
                            <td>{{ $leave->user?->name ?? '-' }}</td>
                            <td>{{ ucfirst($leave->type) }}</td>
                            <td>{{ $leave->from_date->format('d M Y') }}</td>
                            <td>{{ $leave->to_date->format('d M Y') }}</td>
                            <td>
                                <span class="badge badge-sm {{ $leave->status === 'approved' ? 'badge-success' : 'badge-danger' }}">
                                    {{ ucfirst($leave->status) }}
                                </span>
                            </td>
                            <td>{{ $leave->reviewer?->name ?? '-' }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($leave->review_note ?? '-', 40) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-2">No decisions yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @endif
    </div>
</div>