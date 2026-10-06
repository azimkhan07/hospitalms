<div>
    <div class="content">
        <div class="container">
            <div class="page-title">
                <h3 class="text-info">Accountant Desk</h3>
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

            <div class="nav-tabs-custom">
                <ul class="nav nav-tabs">
                    <li class="active"><a href="#" wire:click.prevent="setTab('overview')">Overview</a></li>
                    <li><a href="#" wire:click.prevent="setTab('ledger')">Ledger</a></li>
                    <li><a href="#" wire:click.prevent="setTab('payroll')">Payroll</a></li>
                </ul>

                <div class="tab-content">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="small-box bg-red">
                                <div class="inner">
                                    <h3>{{ number_format($outstanding, 2) }}</h3>
                                    <p>Outstanding receivables</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="small-box bg-green">
                                <div class="inner">
                                    <h3>{{ number_format($collectedToday, 2) }}</h3>
                                    <p>Collected today</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="small-box bg-yellow">
                                <div class="inner">
                                    <h3>{{ number_format($salariesDue, 2) }}</h3>
                                    <p>Salaries due · {{ $month }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if ($tab === 'overview')
                        <div class="box box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title">Open invoices</h3>
                            </div>
                            <div class="box-body table-responsive p-0">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Invoice</th>
                                            <th>Patient</th>
                                            <th>Amount</th>
                                            <th>Paid</th>
                                            <th>Due</th>
                                            <th class="text-right">Record payment</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($openBills as $bill)
                                            <tr>
                                                <td>{{ $bill->invoice_no ?: 'INV-'.$bill->id }}</td>
                                                <td>{{ $bill->patient?->name }}</td>
                                                <td>{{ number_format($bill->amount, 2) }}</td>
                                                <td>
                                                    {{ number_format((float) $bill->payments()->where('status', 'paid')->sum('amount'), 2) }}
                                                    @if ($bill->tax > 0)
                                                        <small class="text-muted">(+{{ number_format($bill->tax, 2) }} GST)</small>
                                                    @endif
                                                </td>
                                                <td>
                                                    <span class="label {{ $bill->amountDue() > 0 ? 'label-danger' : 'label-success' }}">
                                                        {{ number_format($bill->amountDue(), 2) }}
                                                    </span>
                                                </td>
                                                <td class="text-right">
                                                    @if ($receiveBillId === $bill->id)
                                                        <div class="input-group" style="max-width: 320px; margin-left: auto;">
                                                            <input type="number" step="0.01" min="0.01"
                                                                class="form-control" placeholder="Amount"
                                                                wire:model.lazy="receiveAmount">
                                                            <span class="input-group-btn">
                                                                <select class="form-control" wire:model="receiveMode">
                                                                    <option value="cash">Cash</option>
                                                                    <option value="card">Card</option>
                                                                    <option value="cheque">Cheque</option>
                                                                    <option value="online">Online</option>
                                                                </select>
                                                                <button class="btn btn-success" wire:click="recordPayment">
                                                                    Save
                                                                </button>
                                                            </span>
                                                        </div>
                                                    @else
                                                        <button class="btn btn-sm btn-outline-info"
                                                            wire:click="accept({{ $bill->id }})">Collect</button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-muted text-center">No open invoices.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="box box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title">Recent ledger movement</h3>
                            </div>
                            <div class="box-body table-responsive p-0">
                                @include('livewire.admins.partials.accounting-ledger-rows')
                            </div>
                        </div>
                    @endif

                    @if ($tab === 'ledger')
                        <div class="box box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title">Ledger — every money movement</h3>
                            </div>
                            <div class="box-body table-responsive p-0">
                                @include('livewire.admins.partials.accounting-ledger-rows')
                                {{ $vouchers->links() }}
                            </div>
                        </div>
                    @endif

                    @if ($tab === 'payroll')
                        <div class="row">
                            <div class="col-md-4">
                                <div class="box box-primary">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">Run payroll</h3>
                                    </div>
                                    <div class="box-body">
                                        <form wire:submit.prevent="generatePayroll">
                                            <div class="form-group">
                                                <label>Month</label>
                                                <input type="month" class="form-control" required
                                                    wire:model="month">
                                            </div>
                                            <button type="submit" class="btn btn-primary">Generate salary
                                                vouchers</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="box box-primary">
                                    <div class="box-header with-border">
                                        <h3 class="box-title">Salary vouchers · {{ $month }}</h3>
                                    </div>
                                    <div class="box-body table-responsive p-0">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Employee</th>
                                                    <th>Gross</th>
                                                    <th>Net</th>
                                                    <th>Status</th>
                                                    <th class="text-right">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($salaries as $salary)
                                                    <tr>
                                                        <td>{{ $salary->employee?->name }}</td>
                                                        <td>{{ number_format($salary->gross, 2) }}</td>
                                                        <td>{{ number_format($salary->net, 2) }}</td>
                                                        <td>
                                                            <span class="label {{ $salary->isPaid() ? 'label-success' : 'label-warning' }}">
                                                                {{ ucfirst($salary->status) }}
                                                            </span>
                                                        </td>
                                                        <td class="text-right">
                                                            @unless ($salary->isPaid())
                                                                <button class="btn btn-sm btn-success"
                                                                    wire:click="paySalary({{ $salary->id }})"
                                                                    onclick="return confirm('Pay this salary and post the expense?')">
                                                                    Pay
                                                                </button>
                                                            @endunless
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="5" class="text-muted text-center">
                                                            No salary vouchers for this month yet. Run payroll.
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                        @if ($salaries instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                                            {{ $salaries->links() }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>