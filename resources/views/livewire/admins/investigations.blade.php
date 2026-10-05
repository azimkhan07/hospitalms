<div class="box box-primary">
    <div class="box-header d-flex align-items-center justify-content-between flex-wrap">
        <h3 class="box-title"><i class="fas fa-calculator text-info mr-1"></i> Investigation Rate Card</h3>
        <div class="d-flex align-items-center">
            <input type="search" class="form-control form-control-sm mr-2" style="max-width:200px"
                placeholder="Search test, code, department..." wire:model.live.debounce.300ms="search">
            <select class="form-control form-control-sm mr-2" style="max-width:180px" wire:model.live="filterCalc">
                <option value="">All calculation types</option>
                @foreach ($calcTypes as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            <button type="button" class="btn btn-xs btn-default mr-2" onclick="window.print()">
                <i class="fas fa-print"></i> Print rate card
            </button>
            @if ($canManage)
                <button type="button" class="btn btn-xs btn-primary" wire:click="createTest">
                    <i class="fas fa-plus"></i> Add Test
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

        {{-- Live calculator: the whole reason this screen exists. --}}
        <div class="card card-outline card-info mb-3 no-print">
            <div class="card-header py-2 d-flex align-items-center justify-content-between">
                <strong style="font-size:13px"><i class="fas fa-calculator mr-1"></i> Charge calculator</strong>
                <span class="text-muted" style="font-size:12px">
                    Change anything below and the arithmetic updates.
                </span>
            </div>
            <div class="card-body py-3">
                <div class="form-row align-items-end">
                    <div class="col-md-2 form-group mb-0">
                        <label style="font-size:12px">Units</label>
                        <input type="number" min="1" class="form-control form-control-sm"
                            wire:model.live="calcUnits">
                    </div>
                    <div class="col-md-2 form-group mb-0">
                        <label style="font-size:12px">Urgent</label>
                        <select class="form-control form-control-sm" wire:model.live="calcUrgent">
                            <option value="0">Routine</option>
                            <option value="1">Urgent</option>
                        </select>
                    </div>
                    <div class="col-md-2 form-group mb-0">
                        <label style="font-size:12px">Discount</label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                            wire:model.live.debounce.400ms="calcDiscount">
                    </div>
                    <div class="col-md-2 form-group mb-0">
                        <label style="font-size:12px">Tax %</label>
                        <input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm"
                            wire:model.live.debounce.400ms="calcTax">
                    </div>
                    @if ($preview)
                        <div class="col-md-4 mb-0">
                            <label style="font-size:12px">Payable</label>
                            <div class="hms-quote">
                                <strong style="font-size:18px">{{ $preview['total'] }}</strong>
                                <div class="text-muted" style="font-size:11px">{{ $preview['formula'] }}</div>
                            </div>
                        </div>
                    @else
                        <div class="col-md-4 mb-0">
                            <div class="text-muted" style="font-size:12px">
                                Open a test to see what it costs for these numbers.
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if ($showForm)
            @php $editing = $editingId !== null; @endphp
            <form class="card card-outline card-primary mb-3 no-print" wire:submit.prevent="saveTest">
                <div class="card-header py-2 d-flex align-items-center justify-content-between">
                    <strong>{{ $editing ? 'Edit test' : 'New test' }}</strong>
                    @if ($editing)
                        <button type="button" class="btn btn-xs btn-default" wire:click="$set('showCalculator', true)">
                            <i class="fas fa-calculator"></i> Price this test
                        </button>
                    @endif
                </div>
                <div class="card-body py-3">
                    <div class="form-row">
                        <div class="col-md-4 form-group">
                            <label style="font-size:12px">Test name *</label>
                            <input type="text" class="form-control form-control-sm @error('name') is-invalid @enderror"
                                wire:model="name" placeholder="e.g. Complete Blood Count">
                            @error('name') <span class="text-danger text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-2 form-group">
                            <label style="font-size:12px">Code</label>
                            <input type="text" class="form-control form-control-sm" wire:model="code">
                        </div>
                        <div class="col-md-3 form-group">
                            <label style="font-size:12px">Machine</label>
                            <select class="form-control form-control-sm" wire:model="machine_id">
                                <option value="">No machine</option>
                                @foreach ($machines as $m)
                                    <option value="{{ $m->id }}">{{ $m->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label style="font-size:12px">Department</label>
                            <input type="text" class="form-control form-control-sm" wire:model="department">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="col-md-2 form-group">
                            <label style="font-size:12px">Sample type</label>
                            <input type="text" class="form-control form-control-sm" wire:model="sample_type"
                                placeholder="e.g. Blood">
                        </div>
                        <div class="col-md-2 form-group">
                            <label style="font-size:12px">Turnaround (h)</label>
                            <input type="number" min="1" class="form-control form-control-sm"
                                wire:model="turnaround_hours">
                        </div>
                        <div class="col-md-2 form-group">
                            <label style="font-size:12px">Base rate *</label>
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                wire:model="base_rate">
                        </div>
                        <div class="col-md-2 form-group">
                            <label style="font-size:12px">Per unit rate *</label>
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                wire:model="per_unit_rate">
                        </div>
                        <div class="col-md-2 form-group">
                            <label style="font-size:12px">Max units</label>
                            <input type="number" min="1" class="form-control form-control-sm"
                                wire:model="max_units">
                        </div>
                        <div class="col-md-2 form-group">
                            <label style="font-size:12px">Urgent factor</label>
                            <input type="number" step="0.05" min="1" max="10" class="form-control form-control-sm"
                                wire:model="urgent_factor">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="col-md-4 form-group">
                            <label style="font-size:12px">How the charge is worked out *</label>
                            <select class="form-control form-control-sm" wire:model="calc_type">
                                @foreach ($calcTypes as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label style="font-size:12px">Active</label>
                            <select class="form-control form-control-sm" wire:model="is_active">
                                <option value="1">On the rate card</option>
                                <option value="0">Retired</option>
                            </select>
                        </div>
                    </div>

                    <div class="d-flex">
                        <button type="submit" class="btn btn-xs btn-primary mr-2">Save test</button>
                        <button type="button" class="btn btn-xs btn-default" wire:click="cancelForm">Cancel</button>
                    </div>
                </div>
            </form>
        @endif

        <div class="table-responsive">
            <table class="table table-bordered table-striped table-sm mb-0" style="font-size:13px">
                <thead>
                    <tr>
                        <th>Test</th>
                        <th>Machine</th>
                        <th>Calculation</th>
                        <th>Base</th>
                        <th>Per unit</th>
                        <th>Urgent</th>
                        <th>Used</th>
                        <th>State</th>
                        @if ($canManage)
                            <th class="text-right no-print">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tests as $test)
                        <tr class="{{ $test->is_active ? '' : 'text-muted' }}">
                            <td>
                                <strong>{{ $test->name }}</strong>
                                @if ($test->code)
                                    <span class="text-muted">({{ $test->code }})</span>
                                @endif
                                @if ($test->department)
                                    <div class="text-muted" style="font-size:11px">{{ $test->department }}</div>
                                @endif
                            </td>
                            <td>{{ $test->machine?->name ?? '-' }}</td>
                            <td>{{ $calcTypes[$test->calc_type] ?? $test->calc_type }}</td>
                            <td>{{ number_format((float) $test->base_rate, 2) }}</td>
                            <td>{{ number_format((float) $test->per_unit_rate, 2) }}</td>
                            <td>{{ rtrim(rtrim(number_format((float) $test->urgent_factor, 2), '0'), '.') }}x</td>
                            <td>{{ $test->reports_count }}</td>
                            <td>
                                <span class="label label-{{ $test->is_active ? 'success' : 'default' }}">
                                    {{ $test->is_active ? 'On card' : 'Retired' }}
                                </span>
                            </td>
                            @if ($canManage)
                                <td class="text-right no-print">
                                    <button type="button" class="btn btn-xs btn-outline-info"
                                        wire:click="editTest({{ $test->id }})" title="Edit">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <button type="button" class="btn btn-xs btn-outline-danger"
                                        wire:click="deleteTest({{ $test->id }})" title="Remove">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canManage ? 9 : 8 }}" class="text-center text-muted py-3">
                                No test has been added to the rate card yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($tests->hasPages())
            <div class="mt-2 no-print">{{ $tests->links() }}</div>
        @endif
    </div>
</div>

