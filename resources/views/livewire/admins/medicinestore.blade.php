<div>
    <div class="content">
        <div class="container-fluid">
            <div class="row page-title">
                <div class="col">
                    <h3 class="text-info">Pharmacy & Store</h3>
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary" wire:click="$set('showStockIn', false)">Add Medicine</button>
                </div>
            </div>

            @if (session()->has('message'))
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    {{ session('message') }}
                </div>
            @endif

            @if ($alerts['expired'] || $alerts['soon30'] || $alerts['soon60'] || $alerts['soon90'] || $alerts['low'] || $alerts['out'])
                <div class="row">
                    <div class="col-md-3 col-6">
                        <div class="small-box bg-danger">
                            <div class="inner"><h3>{{ $alerts['expired'] }}</h3><p>Expired in stock</p></div>
                            <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                            <a href="{{ route('admin_expired_medicines') }}" class="small-box-footer">Write off <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    @foreach (['soon30' => 'Expiring 30d', 'soon60' => 'Expiring 60d', 'soon90' => 'Expiring 90d', 'low' => 'Low stock', 'out' => 'Out of stock'] as $key => $label)
                        <div class="col-md-3 col-6">
                            <div class="small-box bg-warning">
                                <div class="inner"><h3>{{ $alerts[$key] }}</h3><p>{{ $label }}</p></div>
                                <div class="icon"><i class="fas @if (in_array($key, ['low', 'out'])) fa-box-open @else fa-clock @endif"></i></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="row">
                <div class="col-md-4">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title">{{ $edit_medicine_id ? 'Update' : 'Add' }} medicine (one batch per row)</h3>
                        </div>
                        <div class="box-body">
                            <form wire:submit.prevent="add_medicine" id="add_medicine_form">
                                @if ($edit_medicine_id)
                                    <input type="hidden" wire:model="edit_medicine_id">
                                    <button type="button" class="btn btn-default btn-sm mb-2" wire:click="resetForm">Cancel edit</button>
                                @endif
                                <div class="form-group">
                                    <label>Name *</label>
                                    <input type="text" wire:model.debounce.300ms="name" class="form-control" placeholder="Panadol Extra, Amoxil 500...">
                                    @error('name')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                                <div class="form-group">
                                    <label>Code / Stock id *</label>
                                    <input type="text" wire:model.debounce.300ms="code" class="form-control" placeholder="AMX-500">
                                    @error('code')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                                <div class="form-group">
                                    <label>Generic name</label>
                                    <input type="text" wire:model.debounce.300ms="generic" class="form-control" placeholder="Paracetamol">
                                </div>
                                <div class="form-group">
                                    <label>Composition / strength</label>
                                    <input type="text" wire:model.debounce.300ms="composition" class="form-control" placeholder="Paracetamol 500mg + Caffeine 65mg">
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label>Selling price *</label>
                                            <input type="number" step="0.01" wire:model.debounce.300ms="price" class="form-control" placeholder="35.00">
                                            @error('price')<span class="text-danger">{{ $message }}</span>@enderror
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label>MRP</label>
                                            <input type="number" step="0.01" wire:model.debounce.300ms="mrp" class="form-control" placeholder="40.00">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label>Manufacturer</label>
                                            <input type="text" wire:model.debounce.300ms="manufacturer" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label>Supplier</label>
                                            <input type="text" wire:model.debounce.300ms="supplier" class="form-control">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label>Batch no</label>
                                            <input type="text" wire:model.debounce.300ms="batch_no" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label>Expiry</label>
                                            <input type="date" wire:model.debounce.300ms="expiry_date" class="form-control">
                                            @error('expiry_date')<span class="text-danger">{{ $message }}</span>@enderror
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label>Mfg date</label>
                                            <input type="date" wire:model.debounce.300ms="mfg_date" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label>Reorder level</label>
                                            <input type="number" min="0" wire:model.debounce.300ms="reorder_level" class="form-control" placeholder="20">
                                        </div>
                                    </div>
                                </div>
                                @if (! $edit_medicine_id)
                                    <div class="form-group">
                                        <label>Opening stock (write via the ledger)</label>
                                        <input type="number" min="0" wire:model.debounce.300ms="quantity" class="form-control" placeholder="100">
                                    </div>
                                @endif
                                <button type="submit" class="btn btn-primary">{{ $button_text }}</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title">Stock</h3>
                            <div class="box-tools pull-right">
                                <div class="input-group input-group-sm" style="width: 220px;">
                                    <input type="text" class="form-control" wire:model.debounce.300ms="search" placeholder="Search name, code, batch...">
                                </div>
                            </div>
                        </div>
                        <div class="box-body table-responsive no-padding">
                            <table class="table table-hover table-striped">
                                <thead>
                                    <tr>
                                        <th class="text-capitalize">Medicine</th>
                                        <th>Batch / Expiry</th>
                                        <th class="text-right">Price</th>
                                        <th class="text-right">In stock</th>
                                        <th>Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($medicines as $medicine)
                                        <tr>
                                            <td>
                                                <span class="text-bold">{{ $medicine->name }}</span>
                                                <div class="text-muted small">{{ $medicine->code }} @if ($medicine->generic)· {{ $medicine->generic }} @endif</div>
                                            </td>
                                            <td>
                                                {{ $medicine->batch_no }}
                                                <div class="text-muted small">exp {{ $medicine->expiry_date?->format('d M Y') ?? 'never' }}</div>
                                            </td>
                                            <td class="text-right">{{ number_format((float) ($medicine->mrp ?? $medicine->price), 2) }}</td>
                                            <td class="text-right">{{ $medicine->stock ?? 0 }}</td>
                                            <td>
                                                @if (($medicine->stock ?? 0) <= 0)
                                                    <span class="badge bg-danger">out</span>
                                                @elseif ($medicine->isLow())
                                                    <span class="badge bg-warning">low</span>
                                                @else
                                                    <span class="badge bg-success">ok</span>
                                                @endif
                                            </td>
                                            <td class="text-center text-nowrap">
                                                <button class="btn btn-outline-info btn-xs" wire:click="openLedger({{ $medicine->id }})" title="Ledger"><i class="fas fa-book-open"></i></button>
                                                @if ($canManage)
                                                    <button class="btn btn-outline-success btn-xs" wire:click="openStockIn({{ $medicine->id }})" title="Stock in"><i class="fas fa-plus"></i></button>
                                                    <button class="btn btn-outline-danger btn-xs" wire:click="openWriteOff({{ $medicine->id }})" title="Write off"><i class="fas fa-times"></i></button>
                                                    <button class="btn btn-outline-secondary btn-xs" wire:click="edit({{ $medicine->id }})" title="Edit"><i class="fas fa-pen"></i></button>
                                                    <button class="btn btn-outline-danger btn-xs" wire:click="delete({{ $medicine->id }})" onclick="return confirm('Remove this medicine?')" title="Delete"><i class="fas fa-trash"></i></button>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-center text-muted">No medicines yet.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                            {{ $medicines->links() }}
                        </div>
                    </div>
                </div>
            </div>

            @if ($showStockIn && $stockInId)
                <div class="modal show d-block" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Stock in · {{ $medicines->firstWhere('id', $stockInId)?->name ?? '' }}</h5>
                                <button type="button" class="close" wire:click="$set('showStockIn', false)"><span>&times;</span></button>
                            </div>
                            <div class="modal-body">
                                <div class="form-group">
                                    <label>Units received</label>
                                    <input type="number" min="1" class="form-control" wire:model.debounce.300ms="inQty">
                                    @error('inQty')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                                <div class="form-group">
                                    <label>Note</label>
                                    <input type="text" class="form-control" wire:model.debounce.300ms="inNote" placeholder="GRN #102, supplier delivery...">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button class="btn btn-secondary" wire:click="$set('showStockIn', false)">Close</button>
                                <button class="btn btn-success" wire:click="recordStockIn">Add to stock</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-backdrop show"></div>
            @endif

            @if ($writeOffId)
                <div class="modal show d-block" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Write off · {{ $medicines->firstWhere('id', $writeOffId)?->name ?? '' }}</h5>
                                <button type="button" class="close" wire:click="$set('writeOffId', null)"><span>&times;</span></button>
                            </div>
                            <div class="modal-body">
                                <p class="text-muted">Removes stock that is expired or damaged. The ledger keeps the proof.</p>
                                <div class="form-group">
                                    <label>Units</label>
                                    <input type="number" min="1" class="form-control" wire:model.debounce.300ms="writeOffQty">
                                    @error('writeOffQty')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button class="btn btn-secondary" wire:click="$set('writeOffId', null)">Close</button>
                                <button class="btn btn-danger" wire:click="recordWriteOff('expiry')">Write off</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-backdrop show"></div>
            @endif

            @if ($ledgerId && $ledgerMedicine)
                <div class="modal show d-block" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Ledger — {{ $ledgerMedicine->name }} ({{ $ledgerMedicine->code }})</h5>
                                <button type="button" class="close" wire:click="closeLedger"><span>&times;</span></button>
                            </div>
                            <div class="modal-body table-responsive p-0">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>When</th>
                                            <th>Type</th>
                                            <th class="text-right">Qty</th>
                                            <th>Batch</th>
                                            <th>By</th>
                                            <th>Note</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($movements as $movement)
                                            <tr>
                                                <td class="small">{{ $movement->created_at->format('d M Y H:i') }}</td>
                                                <td>
                                                    <span class="badge @if ($movement->isIn()) bg-success @else bg-danger @endif">
                                                        {{ $movement->type }}
                                                    </span>
                                                </td>
                                                <td class="text-right @if ($movement->quantity > 0) text-success @else text-danger @endif">
                                                    {{ $movement->quantity > 0 ? '+'.$movement->quantity : $movement->quantity }}
                                                </td>
                                                <td class="small">{{ $movement->batch_no }}</td>
                                                <td class="small">{{ $movement->user?->name }}</td>
                                                <td class="small text-muted">{{ $movement->note }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="text-center text-muted">No movements recorded.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="modal-footer">
                                <span class="badge bg-info">In stock: {{ $ledgerMedicine->stock ?? 0 }}</span>
                                <button class="btn btn-secondary" wire:click="closeLedger">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-backdrop show"></div>
            @endif
        </div>
    </div>
</div>