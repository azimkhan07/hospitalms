<div class="box box-primary">
    <div class="box-header d-flex align-items-center justify-content-between flex-wrap">
        <h3 class="box-title"><i class="fas fa-sign-out-alt text-info mr-1"></i> Discharge History</h3>
        <div class="d-flex align-items-center">
            <input type="search" class="form-control form-control-sm mr-1" style="max-width:200px"
                placeholder="Search patient..." wire:model.live.debounce.300ms="search">
            <select class="form-control form-control-sm" style="max-width:150px" wire:model="typeFilter">
                <option value="">All types</option>
                <option value="normal">Normal ({{ $counts['normal'] }})</option>
                <option value="referred">Referred ({{ $counts['referred'] }})</option>
                <option value="absconded">Absconded ({{ $counts['absconded'] }})</option>
            </select>
        </div>
    </div>

    <div class="box-body">
        <div class="row mb-2">
            <div class="col-md-3">
                <div class="border rounded p-2 text-center">
                    <div class="text-muted" style="font-size:10.5px">TOTAL DISCHARGED</div>
                    <div style="font-size:18px;font-weight:700">{{ $totals['count'] }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-2 text-center">
                    <div class="text-muted" style="font-size:10.5px">BILLED VALUE</div>
                    <div style="font-size:18px;font-weight:700">{{ number_format($totals['amount'] ?? 0) }}</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="border rounded p-2 text-center">
                    <div class="text-muted" style="font-size:10.5px">NORMAL</div>
                    <div style="font-size:16px;font-weight:700">{{ $counts['normal'] }}</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="border rounded p-2 text-center">
                    <div class="text-muted" style="font-size:10.5px">REFERRED</div>
                    <div style="font-size:16px;font-weight:700">{{ $counts['referred'] }}</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="border rounded p-2 text-center">
                    <div class="text-muted" style="font-size:10.5px">ABSCONDED</div>
                    <div style="font-size:16px;font-weight:700">{{ $counts['absconded'] }}</div>
                </div>
            </div>
        </div>

        <div class="text-info" wire:loading>Loading..</div>

        <table class="table table-sm table-bordered mb-0">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Blood Group</th>
                    <th>Admitted</th>
                    <th>Discharged</th>
                    <th>Stay (days)</th>
                    <th>Department</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Discount</th>
                    <th>Total</th>
                    <th>Note</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($stays as $s)
                    <tr>
                        <td>{{ $s->patient?->name ?? 'Removed patient' }}</td>
                        <td>{{ $s->patient?->bloodgroup ?? '-' }}</td>
                        <td>{{ optional($s->start_time)->format('d M Y') ?? '-' }}</td>
                        <td>{{ $s->discharged_at?->format('d M Y') ?? '-' }}</td>
                        <td>
                            @if ($s->start_time && $s->discharged_at)
                                {{ $s->start_time->diffInDays($s->discharged_at) + 1 }}
                            @else
                                -
                            @endif
                        </td>
                        <td>{{ $s->room?->department?->name ?? 'Room #'.($s->room_id ?? '-') }}</td>
                        <td>
                            @php
                                $typeLabel = \App\Models\stay::DISCHARGE_TYPES[$s->discharge_type] ?? ucfirst($s->discharge_type ?? 'n/a');
                                $typeBadge = match ($s->discharge_type) {
                                    'normal', 'recovered' => 'success',
                                    'referred' => 'info',
                                    'transferred', 'lama' => 'warning',
                                    default => 'danger',
                                };
                            @endphp
                            <span class="badge badge-sm badge-{{ $typeBadge }}">{{ $typeLabel }}</span>
                        </td>
                        <td>{{ $s->amount ?? '-' }}</td>
                        <td>{{ $s->discount ?? '-' }}</td>
                        <td>{{ $s->total ?? '-' }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($s->discharge_note ?? '-', 40) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="text-center text-muted py-2">No discharge records yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($stays->hasPages())
            <div class="mt-2">{{ $stays->links() }}</div>
        @endif
    </div>
</div>