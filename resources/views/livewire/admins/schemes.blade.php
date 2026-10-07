<div class="box box-primary">
    <div class="box-header d-flex align-items-center justify-content-between flex-wrap">
        <h3 class="box-title"><i class="fas fa-hand-holding-usd text-info mr-1"></i> Government Schemes (Yojna)</h3>
        <div class="d-flex align-items-center">
            <input type="search" class="form-control form-control-sm mr-2" style="max-width:200px"
                placeholder="Search name, code, provider..." wire:model.live.debounce.300ms="search">
            <select class="form-control form-control-sm mr-2" style="max-width:130px" wire:model.live="active">
                <option value="">All schemes</option>
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
            <button type="button" class="btn btn-xs btn-default mr-2" wire:click="resetFilters">Clear</button>
            @if ($canManage)
                <button type="button" class="btn btn-xs btn-primary" wire:click="createScheme">
                    <i class="fas fa-plus"></i> Add Scheme
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
            <form class="card card-outline card-primary mb-3" wire:submit.prevent="saveScheme">
                <div class="card-header py-2">
                    <strong>{{ $editing ? 'Edit scheme' : 'New scheme' }}</strong>
                </div>
                <div class="card-body py-3">
                    <div class="form-row">
                        <div class="col-md-4 form-group">
                            <label style="font-size:12px">Name *</label>
                            <input type="text" class="form-control form-control-sm @error('name') is-invalid @enderror"
                                wire:model="name" placeholder="e.g. Ayushman Bharat (PM-JAY)">
                            @error('name') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-2 form-group">
                            <label style="font-size:12px">Code</label>
                            <input type="text" class="form-control form-control-sm" wire:model="code"
                                placeholder="e.g. PMJAY">
                        </div>
                        <div class="col-md-3 form-group">
                            <label style="font-size:12px">Provider</label>
                            <input type="text" class="form-control form-control-sm" wire:model="provider"
                                placeholder="e.g. State Health Agency">
                        </div>
                        <div class="col-md-3 form-group">
                            <label style="font-size:12px">Coverage</label>
                            <div class="input-group input-group-sm">
                                <select class="form-control form-control-sm" wire:model="coverageType"
                                    style="max-width:90px">
                                    <option value="amount">Amount</option>
                                    <option value="percent">Percent</option>
                                </select>
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                    wire:model="coverageValue" placeholder="0">
                            </div>
                            <small class="text-muted">What the state pays per treatment.</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="col-md-9 form-group">
                            <label style="font-size:12px">Description</label>
                            <input type="text" class="form-control form-control-sm" wire:model="description"
                                placeholder="Which cards / eligibility this scheme covers...">
                        </div>
                        <div class="col-md-3 form-group">
                            <label style="font-size:12px">&nbsp;</label>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="schemeActive"
                                    wire:model="isActive">
                                <label class="form-check-label" for="schemeActive" style="font-size:12px">Active</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex">
                        <button type="submit" class="btn btn-xs btn-primary mr-2">Save scheme</button>
                        <button type="button" class="btn btn-xs btn-default" wire:click="cancelForm">Cancel</button>
                    </div>
                </div>
            </form>
        @endif

        @if ($showIncome && $incomeScheme)
            <form class="card card-outline card-warning mb-3" wire:submit.prevent="recordIncome">
                <div class="card-header py-2">
                    <strong>Record income · {{ $incomeScheme->name }}</strong>
                    <span class="text-muted" style="font-size:11px">(money received from the government under this scheme)</span>
                </div>
                <div class="card-body py-2">
                    <div class="form-row">
                        <div class="col-md-3 form-group mb-0">
                            <label style="font-size:12px">Amount (Rs) *</label>
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                wire:model="incomeAmount">
                            @error('incomeAmount') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-7 form-group mb-0">
                            <label style="font-size:12px">Note</label>
                            <input type="text" class="form-control form-control-sm" wire:model="incomeNote"
                                placeholder="Disbursement reference, month, no. of claims...">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-xs btn-warning mr-2">Book income</button>
                            <button type="button" class="btn btn-xs btn-default" wire:click="cancelIncome">Cancel</button>
                        </div>
                    </div>
                </div>
            </form>
        @endif

        <div class="table-responsive">
            <table class="table table-bordered table-striped table-sm mb-0" style="font-size:13px">
                <thead>
                    <tr>
                        <th>Scheme</th>
                        <th>Provider</th>
                        <th>Coverage</th>
                        <th>Patients</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($schemes as $scheme)
                        <tr>
                            <td>
                                <strong>{{ $scheme->name }}</strong>
                                @if ($scheme->code)
                                    <span class="text-muted">({{ $scheme->code }})</span>
                                @endif
                            </td>
                            <td>{{ $scheme->provider ?: '-' }}</td>
                            <td>{{ $scheme->coverage_label }}</td>
                            <td>{{ $scheme->patients_count }}</td>
                            <td>
                                <span class="label label-{{ $scheme->is_active ? 'success' : 'default' }}">
                                    {{ $scheme->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-right">
                                @if ($canManage)
                                    <button type="button" class="btn btn-xs btn-outline-warning"
                                        wire:click="openIncome({{ $scheme->id }})" title="Record income from government">
                                        <i class="fas fa-coins"></i>
                                    </button>
                                    <button type="button" class="btn btn-xs btn-outline-info"
                                        wire:click="editScheme({{ $scheme->id }})" title="Edit">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <button type="button" class="btn btn-xs btn-outline-danger"
                                        wire:click="deleteScheme({{ $scheme->id }})" title="Remove">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-3">
                                No scheme has been registered yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($schemes->hasPages())
            <div class="mt-2">{{ $schemes->links() }}</div>
        @endif
    </div>
</div>