<div class="box box-primary">
    <div class="box-header d-flex align-items-center justify-content-between flex-wrap">
        <h3 class="box-title"><i class="fas fa-x-ray text-info mr-1"></i> Machines</h3>
        <div class="d-flex align-items-center">
            <input type="search" class="form-control form-control-sm mr-2" style="max-width:200px"
                placeholder="Search name, code, vendor..." wire:model.live.debounce.300ms="search">
            <select class="form-control form-control-sm mr-2" style="max-width:150px" wire:model.live="status">
                <option value="">All statuses</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s }}">{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                @endforeach
            </select>
            <select class="form-control form-control-sm mr-2" style="max-width:160px" wire:model.live="modality">
                <option value="">All modalities</option>
                @foreach ($modalities as $m)
                    <option value="{{ $m }}">{{ $m }}</option>
                @endforeach
            </select>
            <button type="button" class="btn btn-xs btn-default mr-2" wire:click="resetFilters">Clear</button>
            @if ($canManage)
                <button type="button" class="btn btn-xs btn-primary" wire:click="createMachine">
                    <i class="fas fa-plus"></i> Add Machine
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
            <form class="card card-outline card-primary mb-3" wire:submit.prevent="saveMachine">
                <div class="card-header py-2">
                    <strong>{{ $editing ? 'Edit machine' : 'New machine' }}</strong>
                </div>
                <div class="card-body py-3">
                    <div class="form-row">
                        <div class="col-md-4 form-group">
                            <label style="font-size:12px">Name *</label>
                            <input type="text" class="form-control form-control-sm @error('name') is-invalid @enderror"
                                wire:model="name" placeholder="e.g. ICU Ventilator 1">
                            @error('name') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-2 form-group">
                            <label style="font-size:12px">Code</label>
                            <input type="text" class="form-control form-control-sm" wire:model="code">
                        </div>
                        <div class="col-md-3 form-group">
                            <label style="font-size:12px">Modality</label>
                            <select class="form-control form-control-sm" wire:model="modalityChoice">
                                <option value="">Choose</option>
                                @foreach ($modalities as $m)
                                    <option value="{{ $m }}">{{ $m }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label style="font-size:12px">Department</label>
                            <input type="text" class="form-control form-control-sm" wire:model="department"
                                placeholder="e.g. Radiology">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="col-md-3 form-group">
                            <label style="font-size:12px">Room</label>
                            <select class="form-control form-control-sm" wire:model="room_id">
                                <option value="">Not in a room</option>
                                @foreach ($rooms as $room)
                                    <option value="{{ $room->id }}">Room {{ $room->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label style="font-size:12px">Bed</label>
                            <select class="form-control form-control-sm" wire:model="bed_id">
                                <option value="">Not on a bed</option>
                                @foreach ($beds as $bed)
                                    <option value="{{ $bed->id }}">Bed {{ $bed->bed_number }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label style="font-size:12px">Free-text location</label>
                            <input type="text" class="form-control form-control-sm" wire:model="location"
                                placeholder="e.g. ICU Bay 2">
                        </div>
                        <div class="col-md-3 form-group">
                            <label style="font-size:12px">Rate per unit *</label>
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                wire:model="rate">
                            <small class="text-muted">Used when a test bills by machine rate.</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="col-md-3 form-group">
                            <label style="font-size:12px">Serial number</label>
                            <input type="text" class="form-control form-control-sm" wire:model="serial_number">
                        </div>
                        <div class="col-md-3 form-group">
                            <label style="font-size:12px">Vendor</label>
                            <input type="text" class="form-control form-control-sm" wire:model="vendor">
                        </div>
                        <div class="col-md-2 form-group">
                            <label style="font-size:12px">Purchased</label>
                            <input type="date" class="form-control form-control-sm" wire:model="purchase_date">
                        </div>
                        <div class="col-md-2 form-group">
                            <label style="font-size:12px">Warranty ends</label>
                            <input type="date" class="form-control form-control-sm" wire:model="warranty_ends_at">
                        </div>
                        <div class="col-md-2 form-group">
                            <label style="font-size:12px">Status *</label>
                            <select class="form-control form-control-sm" wire:model="machineStatus">
                                @foreach ($statuses as $s)
                                    <option value="{{ $s }}">{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="d-flex">
                        <button type="submit" class="btn btn-xs btn-primary mr-2">Save machine</button>
                        <button type="button" class="btn btn-xs btn-default" wire:click="cancelForm">Cancel</button>
                    </div>
                </div>
            </form>
        @endif

        <div class="table-responsive">
            <table class="table table-bordered table-striped table-sm mb-0" style="font-size:13px">
                <thead>
                    <tr>
                        <th>Machine</th>
                        <th>Modality</th>
                        <th>Department</th>
                        <th>Where</th>
                        <th>Rate</th>
                        <th>Status</th>
                        <th>Warranty</th>
                        @if ($canManage)
                            <th class="text-right">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($machines as $machine)
                        <tr>
                            <td>
                                <strong>{{ $machine->name }}</strong>
                                @if ($machine->code)
                                    <span class="text-muted">({{ $machine->code }})</span>
                                @endif
                            </td>
                            <td>{{ $machine->modality ?: '-' }}</td>
                            <td>{{ $machine->department ?: '-' }}</td>
                            <td>{{ $machine->location_label }}</td>
                            <td>{{ number_format((float) $machine->rate, 2) }}</td>
                            <td>
                                @php $badge = ['working' => 'success', 'under_service' => 'warning', 'retired' => 'default'][$machine->status] ?? 'default'; @endphp
                                <span class="label label-{{ $badge }}">{{ ucfirst(str_replace('_', ' ', $machine->status)) }}</span>
                            </td>
                            <td>
                                @if ($machine->warranty_ends_at)
                                    <span class="{{ $machine->warranty_expired ? 'text-danger' : '' }}">
                                        {{ $machine->warranty_ends_at->format('d M Y') }}
                                        @if ($machine->warranty_expired)
                                            <i class="fas fa-exclamation-triangle"></i> expired
                                        @endif
                                    </span>
                                @else
                                    -
                                @endif
                            </td>
                            @if ($canManage)
                                <td class="text-right">
                                    <button type="button" class="btn btn-xs btn-outline-info"
                                        wire:click="editMachine({{ $machine->id }})" title="Edit">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <button type="button" class="btn btn-xs btn-outline-danger"
                                        wire:click="deleteMachine({{ $machine->id }})" title="Remove">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canManage ? 8 : 7 }}" class="text-center text-muted py-3">
                                No machine has been registered yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($machines->hasPages())
            <div class="mt-2">{{ $machines->links() }}</div>
        @endif
    </div>
</div>