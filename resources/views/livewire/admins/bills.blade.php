<div>
    <div class="content">
        <div class="container">
            <div class="page-title">
                <h3 class="text-info">{{ env('APP_NAME') }} Bill's</h3>
            </div>
            <div>
                @if (session()->has('message'))
                    <div class="alert alert-success">
                        {{ session('message') }}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif
                @if (session()->has('error'))
                    <div class="alert alert-danger">
                        {{ session('error') }}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif
            </div>
            <div class="box box-primary">
                <div class="box-body">
                    <div class="text-info" wire:loading>Loading..</div>
                    <form accept-charset="utf-8" class="shadow rounded p-3" wire:submit.prevent="add_bill()">
                        <div class="text-capitalize bg-dark p-2 shadow mb-3 text-center text-lg text-light rounded">
                            {{ $edit_bill_id ? __('Update Bill') : __('Add New Bill') }}</div>

                        <div class="form-group">
                            <label for="pat">Patient ID</label>
                            <select name="pat" wire:model.lazy="patients_id" class="form-control" required>
                                <option selected>Choose Patient</option>
                                @forelse ($patients as $patient)
                                    <option value="{{ $patient->id }}">{{ $patient->name }}</option>
                                @empty
                                    <option value="null">Null</option>
                                @endforelse
                            </select>
                            @error('patients_id')
                                <span class="text-red-500 text-danger text-xs">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label for="amount">Amount</label>
                                <input type="number" step="0.01" placeholder="Enter Amount KPR" name="amount" wire:model.lazy="amount"
                                    class="form-control">
                                @error('amount')
                                    <span class="text-red-500 text-danger text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="form-group col-md-3">
                                <label for="discount_amount">Discount</label>
                                <input type="number" step="0.01" min="0" name="discount_amount" wire:model.lazy="discount_amount"
                                    class="form-control">
                                @error('discount_amount')
                                    <span class="text-danger text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="form-group col-md-3">
                                <label for="advance_used">Advance used</label>
                                <input type="number" step="0.01" min="0" name="advance_used" wire:model.lazy="advance_used"
                                    class="form-control">
                                @error('advance_used')
                                    <span class="text-danger text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="form-group col-md-3">
                                <label for="remarks">Remarks</label>
                                <input type="text" maxlength="255" name="remarks" wire:model.lazy="remarks"
                                    class="form-control" placeholder="Optional note">
                                @error('remarks')
                                    <span class="text-danger text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group">
                            <input type="submit" class="btn btn-primary" value="{{ $button_text }}">
                            @if ($edit_bill_id)
                                <button type="button" class="btn btn-outline-secondary" wire:click="$set('edit_bill_id', null)">Cancel</button>
                            @endif
                        </div>
                    </form><br>
                    <hr>

                    @if ($itemBill)
                        <div class="box box-solid box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title">
                                    Line items · {{ $itemBill->invoice_no ?: 'INV-'.$itemBill->id }}
                                    <small class="text-muted">({{ $itemBill->patient?->name ?: 'No patient' }})</small>
                                </h3>
                                <div class="box-tools float-right">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="closeItems">Close</button>
                                </div>
                            </div>
                            <div class="box-body">
                                <table class="table table-sm table-hover mb-2">
                                    <thead>
                                        <tr>
                                            <th>Description</th>
                                            <th>Category</th>
                                            <th class="text-right">Qty</th>
                                            <th class="text-right">Rate</th>
                                            <th class="text-right">Amount</th>
                                            <th class="text-center"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($items as $item)
                                            <tr>
                                                <td>{{ $item->description }}</td>
                                                <td><span class="label label-default">{{ $item->categoryLabel() }}</span></td>
                                                <td class="text-right">{{ number_format((float) $item->qty, 2) }}</td>
                                                <td class="text-right">{{ number_format((float) $item->rate, 2) }}</td>
                                                <td class="text-right">{{ number_format((float) $item->amount, 2) }}</td>
                                                <td class="text-center">
                                                    <button wire:click="removeItem({{ $item->id }})"
                                                        onclick="return confirm('{{ __('Are You Sure ?') }}')"
                                                        class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-muted text-center">No line items yet.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>

                                <div class="text-right mb-3">
                                    <strong>Gross:</strong> {{ number_format($itemBill->grossAmount(), 2) }}
                                    &nbsp;·&nbsp; <strong>Net:</strong> {{ number_format($itemBill->netAmount(), 2) }}
                                    &nbsp;·&nbsp; <strong>Due:</strong> {{ number_format($itemBill->amountDue(), 2) }}
                                </div>

                                <form class="form-row align-items-end" wire:submit.prevent="addItem()">
                                    <div class="form-group col-md-5 mb-2">
                                        <label class="mb-0">Description</label>
                                        <input type="text" class="form-control form-control-sm" wire:model.defer="item_description"
                                            placeholder="Charge description">
                                        @error('item_description')
                                            <span class="text-danger text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="form-group col-md-2 mb-2">
                                        <label class="mb-0">Category</label>
                                        <select class="form-control form-control-sm" wire:model.defer="item_category">
                                            @foreach ($categories as $category)
                                                <option value="{{ $category }}">{{ ucfirst($category) }}</option>
                                            @endforeach
                                        </select>
                                        @error('item_category')
                                            <span class="text-danger text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="form-group col-md-1 mb-2">
                                        <label class="mb-0">Qty</label>
                                        <input type="number" step="0.01" min="0.01" class="form-control form-control-sm"
                                            wire:model.defer="item_qty">
                                        @error('item_qty')
                                            <span class="text-danger text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="form-group col-md-2 mb-2">
                                        <label class="mb-0">Rate</label>
                                        <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                            wire:model.defer="item_rate">
                                        @error('item_rate')
                                            <span class="text-danger text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="form-group col-md-2 mb-2">
                                        <button type="submit" class="btn btn-primary btn-sm btn-block">Add item</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif

                    <div class="text-capitalize bg-dark p-2 shadow mb-3 text-center text-lg text-light rounded">
                        {{ __('All Bills') }}</div>
                    <table class="table table-hover table-sm" style="" id="">
                        <thead>
                            <tr>
                                <th class="text-center">Bill ID</th>
                                <th class="text-center">Patient</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Bill Amount <small class="text-warning">KPR</small></th>
                                <th class="text-center">Items</th>
                                <th class="text-center">Discount</th>
                                <th class="text-center">Advance</th>
                                <th class="text-center">Net</th>
                                <th class="text-center">Due</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($bills as $bill)
                                <tr @if ($bill->status == 'paid') class="bg-success" @endif>
                                    <td class="text-center">{{ $bill->id }}</td>
                                    <td class="text-center">{{ $bill->patient?->name ?: ($bill->patients_id ?: 'Null') }}</td>
                                    <td class="text-center">
                                        <span class="label {{ $bill->status == 'paid' ? 'label-success' : 'label-danger' }}">
                                            {{ ucfirst($bill->status) }}
                                        </span>
                                    </td>
                                    <td class="text-center">{{ number_format((float) $bill->amount, 2) }}</td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-secondary" wire:click="openItems({{ $bill->id }})">
                                            {{ $bill->items_count }}
                                        </button>
                                    </td>
                                    <td class="text-center">{{ number_format((float) $bill->discount_amount, 2) }}</td>
                                    <td class="text-center">{{ number_format((float) $bill->advance_used, 2) }}</td>
                                    <td class="text-center">{{ number_format($bill->netAmount(), 2) }}</td>
                                    <td class="text-center">
                                        <span class="label {{ $bill->amountDue() > 0 ? 'label-warning' : 'label-success' }}">
                                            {{ number_format($bill->amountDue(), 2) }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        @if (hms_can('printout') && isset($bill->id))
                                            <a href="{{ route('admin_print_invoice', $bill->id) }}" target="_blank"
                                                class="btn btn-outline-info btn-rounded" title="Print A4 invoice"><i
                                                    class="fas fa-print"></i></a>
                                        @endif
                                        <button wire:click="openItems({{ $bill->id }})"
                                            class="btn btn-outline-secondary btn-rounded" title="Line items"><i
                                                class="fas fa-list"></i></button>
                                        <button wire:click="finaliseStay({{ $bill->id }})"
                                            onclick="return confirm('{{ __('Finalise bill from the latest IPD stay?') }}')"
                                            class="btn btn-outline-warning btn-rounded" title="Finalise from IPD stay"><i
                                                class="fas fa-procedures"></i></button>
                                        <button wire:click="edit({{ $bill->id }})"
                                            class="btn btn-outline-info btn-rounded"><i class="fas fa-pen"></i></button>
                                        <button wire:click="delete({{ $bill->id }})"
                                            onclick="return confirm('{{ __('Are You Sure ?') }}')"
                                            class="btn btn-outline-danger btn-rounded"><i
                                                class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-warning text-center">{{ __('Null') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    {{ $bills->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
