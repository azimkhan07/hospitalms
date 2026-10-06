<div class="table-responsive p-0">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>Date</th>
                <th>Memo</th>
                <th>Ref</th>
                <th>Type</th>
                <th class="text-right">Amount</th>
                <th class="text-right">Balance</th>
            </tr>
        </thead>
        <tbody>
            @php $running = 0.0; @endphp
            @forelse ($vouchers as $voucher)
                @php
                    if ($voucher->isIncome()) {
                        $running += (float) $voucher->amount;
                    } else {
                        $running -= (float) $voucher->amount;
                    }
                @endphp
                <tr>
                    <td>{{ $voucher->occurred_at?->format('d M Y') }}</td>
                    <td>
                        {{ $voucher->title }}
                        @if ($voucher->note)
                            <br><small class="text-muted">{{ $voucher->note }}</small>
                        @endif
                    </td>
                    <td>{{ $voucher->ref_type }}#{{ $voucher->ref_id }}</td>
                    <td>
                        <span class="label {{ $voucher->isIncome() ? 'label-success' : 'label-danger' }}">
                            {{ ucfirst($voucher->type) }}
                        </span>
                    </td>
                    <td class="text-right">{{ number_format($voucher->amount, 2) }}</td>
                    <td class="text-right">{{ number_format($running, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-muted text-center">No ledger movement yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>