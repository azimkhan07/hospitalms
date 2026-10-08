<div>
    <div class="content">
        <div class="container">
            <div class="page-title">
                <h3 class="text-info"><i class="fa fa-print"></i> Print Center</h3>
            </div>

            @if (session()->has('message'))
                <div class="alert alert-success">
                    {{ session('message') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            <div class="box box-primary">
                <div class="box-body">
                    <form method="GET" action="{{ route('admin_print_center') }}" class="form-inline mb-3">
                        <div class="form-group mr-2">
                            <label class="mr-1">Type</label>
                            <select name="type" class="form-control form-control-sm">
                                @foreach (['invoice' => 'Final Invoice (A4)', 'medicine-slip' => 'Daily Medicine Slip (Thermal)', 'case-paper' => 'Case Paper (A5)', 'prescription' => 'Prescription (A5)'] as $value => $label)
                                    <option value="{{ $value }}" @selected((string) $request->query('type', 'invoice') === $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group mr-2">
                            <label class="mr-1">Search</label>
                            <input type="text" name="search" value="{{ $request->query('search') }}" class="form-control form-control-sm" placeholder="Patient name / phone">
                        </div>
                        @if ($request->query('type', 'invoice') === 'medicine-slip')
                            <div class="form-group mr-2">
                                <label class="mr-1">Date</label>
                                <input type="date" name="date" value="{{ $request->query('date', today()->toDateString()) }}" class="form-control form-control-sm">
                            </div>
                        @endif
                        <button class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Filter</button>
                        <a href="{{ route('admin_print_center') }}" class="btn btn-default btn-sm ml-2">Reset</a>
                    </form>

                    <table class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th class="text-center">#</th>
                                <th>Patient</th>
                                <th>Document</th>
                                <th>Date</th>
                                <th>Print</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr>
                                    @if ($type === 'invoice')
                                        <td class="text-center">{{ $row->id }}</td>
                                        <td>{{ $row->patient?->name ?? 'Walk-in' }}</td>
                                        <td>Invoice #{{ $row->invoice_no ?? $row->id }}</td>
                                        <td>{{ optional($row->paid_at ?? $row->created_at)->format('d M Y') }}</td>
                                        <td>
                                            <a href="{{ route('admin_print_invoice', $row->id) }}" target="_blank" class="btn btn-outline-primary btn-xs">
                                                <i class="fa fa-file-pdf"></i> A4
                                            </a>
                                        </td>
                                    @elseif ($type === 'medicine-slip')
                                        <td class="text-center">{{ $row->id }}</td>
                                        <td>{{ $row->name }}</td>
                                        <td>{{ $row->stays->filter(fn ($s) => blank($s->end_time))->first()?->bedLabel() ?? 'Slip' }}</td>
                                        <td>{{ $date }}</td>
                                        <td>
                                            <a href="{{ route('admin_print_medicine_slip', [$row->id, 'date' => $date]) }}" target="_blank" class="btn btn-outline-primary btn-xs">
                                                <i class="fa fa-file-pdf"></i> Thermal
                                            </a>
                                        </td>
                                    @elseif ($type === 'case-paper')
                                        <td class="text-center">{{ $row->id }}</td>
                                        <td>{{ $row->name }}</td>
                                        <td>Case Paper</td>
                                        <td>{{ $row->created_at->format('d M Y') }}</td>
                                        <td>
                                            <a href="{{ route('admin_print_case_paper', $row->id) }}" target="_blank" class="btn btn-outline-primary btn-xs">
                                                <i class="fa fa-file-pdf"></i> A5
                                            </a>
                                        </td>
                                    @else
                                        <td class="text-center">{{ $row->id }}</td>
                                        <td>{{ $row->patient?->name ?? '-' }}</td>
                                        <td>Prescription</td>
                                        <td>{{ optional($row->issued_at)->format('d M Y') }}</td>
                                        <td>
                                            <a href="{{ route('admin_print_prescription', $row->id) }}" target="_blank" class="btn btn-outline-primary btn-xs">
                                                <i class="fa fa-file-pdf"></i> A5
                                            </a>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-warning text-center">No records found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    {{ $rows->links() }}
                </div>
            </div>
        </div>
    </div>
</div>