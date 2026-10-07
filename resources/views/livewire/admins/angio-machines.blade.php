<div class="box box-primary">
    <div class="box-header d-flex align-items-center justify-content-between flex-wrap">
        <h3 class="box-title"><i class="fas fa-heartbeat text-info mr-1"></i> Angio / Cath Lab</h3>
        <div class="d-flex align-items-center">
            <input type="search" class="form-control form-control-sm mr-2" style="max-width:200px"
                placeholder="Search name, code, model..." wire:model.live.debounce.300ms="search">
            <select class="form-control form-control-sm mr-2" style="max-width:150px" wire:model.live="status">
                <option value="">All statuses</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s }}">{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                @endforeach
            </select>
            <button type="button" class="btn btn-xs btn-default mr-2" wire:click="resetFilters">Clear</button>
            @if ($canManage)
                <button type="button" class="btn btn-xs btn-primary" wire:click="createMachine">
                    <i class="fas fa-plus"></i> Add Angio Machine
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
                    <strong>{{ $editing ? 'Edit angio machine' : 'New angio machine' }}</strong>
                </div>
                <div class="card-body py-3">
                    <div class="form-row">
                        <div class="col-md-4 form-group">
                            <label style="font-size:12px">Name *</label>
                            <input type="text" class="form-control form-control-sm @error('name') is-invalid @enderror"
                                wire:model="name" placeholder="e.g. Cath Lab 1">
                            @error('name') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-2 form-group">
                            <label style="font-size:12px">Code</label>
                            <input type="text" class="form-control form-control-sm" wire:model="code">
                        </div>
                        <div class="col-md-3 form-group">
                            <label style="font-size:12px">Manufacturer</label>
                            <input type="text" class="form-control form-control-sm" wire:model="manufacturer"
                                placeholder="e.g. Philips">
                        </div>
                        <div class="col-md-3 form-group">
                            <label style="font-size:12px">Model</label>
                            <input type="text" class="form-control form-control-sm" wire:model="model"
                                placeholder="e.g. Azurion 7">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="col-md-3 form-group">
                            <label style="font-size:12px">Serial number</label>
                            <input type="text" class="form-control form-control-sm" wire:model="serial_number">
                        </div>
                        <div class="col-md-3 form-group">
                            <label style="font-size:12px">Vendor / supplier</label>
                            <input type="text" class="form-control form-control-sm" wire:model="vendor">
                        </div>
                        <div class="col-md-3 form-group">
                            <label style="font-size:12px">Location</label>
                            <input type="text" class="form-control form-control-sm" wire:model="location"
                                placeholder="e.g. Cath Lab, Ground floor">
                        </div>
                        <div class="col-md-2 form-group">
                            <label style="font-size:12px">Installed on</label>
                            <input type="date" class="form-control form-control-sm" wire:model="installed_at">
                        </div>
                        <div class="col-md-1 form-group">
                            <label style="font-size:12px">Status</label>
                            <select class="form-control form-control-sm" wire:model="machineStatus">
                                @foreach ($statuses as $s)
                                    <option value="{{ $s }}">{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="col-md-3 form-group">
                            <label style="font-size:12px">Procedure rate</label>
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                wire:model="rate" placeholder="Optional">
                            <small class="text-muted">Used when billing a procedure done on this machine.</small>
                        </div>
                        <div class="col-md-9 form-group">
                            <label style="font-size:12px">Notes</label>
                            <input type="text" class="form-control form-control-sm" wire:model="notes"
                                placeholder="Condition, cautions, emergency stock near cath lab...">
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
                        <th>Manufacturer / Model</th>
                        <th>Where</th>
                        <th>Rate</th>
                        <th>Status</th>
                        <th>Treatments</th>
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
                            <td>{{ trim(($machine->manufacturer ?: '').' '.($machine->model ?: '')) ?: '-' }}</td>
                            <td>
                                {{ $machine->location ?: '-' }}
                                @if ($machine->installed_at)
                                    <br><span class="text-muted" style="font-size:11px">Installed {{ $machine->installed_at->format('d M Y') }}</span>
                                @endif
                            </td>
                            <td>{{ $machine->rate !== null ? number_format((float) $machine->rate, 2) : '-' }}</td>
                            <td>
                                @php $badge = ['working' => 'success', 'under_service' => 'warning', 'retired' => 'default'][$machine->status] ?? 'default'; @endphp
                                <span class="label label-{{ $badge }}">{{ ucfirst(str_replace('_', ' ', $machine->status)) }}</span>
                            </td>
                            <td>{{ $machine->appointments_count }}</td>
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
                            <td colspan="{{ $canManage ? 7 : 6 }}" class="text-center text-muted py-3">
                                No angio machine has been registered yet.
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