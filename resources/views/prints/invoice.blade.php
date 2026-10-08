<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $bill->invoice_no }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #24303c; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 6px 8px; text-align: left; vertical-align: top; }
        .header { border-bottom: 3px solid #0f7fd4; padding-bottom: 12px; margin-bottom: 14px; }
        .brand { font-size: 22px; font-weight: bold; color: #0b3c66; }
        .brand small { display: block; font-size: 11px; color: #61748a; font-weight: normal; }
        .doc-title { font-size: 16px; font-weight: bold; color: #0f7fd4; text-align: right; }
        .doc-title small { display: block; font-size: 11px; color: #61748a; }
        .meta { font-size: 11px; color: #61748a; }
        .box { border: 1px solid #e3ebf3; border-radius: 4px; padding: 10px 12px; margin-bottom: 12px; }
        .box h4 { margin: 0 0 8px; font-size: 12px; color: #0b3c66; text-transform: uppercase; letter-spacing: .5px; }
        .items th { background: #0f7fd4; color: #fff; font-size: 11px; border: 1px solid #0b3c66; }
        .items td { border: 1px solid #e3ebf3; font-size: 12px; }
        .totals { text-align: right; }
        .totals td { border-bottom: 1px solid #e3ebf3; }
        .grand { font-size: 14px; font-weight: bold; background: #f2f8fd; }
        .status-row { margin-top: 12px; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; }
        .badge-paid { background: #e6f6ec; color: #1a7f4b; }
        .badge-pending { background: #fff4e0; color: #a26b10; }
        .sign { margin-top: 24px; }
        .sign td { text-align: center; font-size: 11px; color: #61748a; border-top: 1px solid #e3ebf3; padding-top: 46px; }
        .legend { margin-top: 14px; font-size: 10px; color: #61748a; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td class="brand">
                {{ config('app.name', 'HospitalMS') }}
                <small>{{ config('hms.address', '') }}</small>
            </td>
            <td class="doc-title">
                FINAL INVOICE
                <small>Invoice #{{ $bill->invoice_no ?? '#'.$bill->id }}</small>
            </td>
        </tr>
    </table>

    <table>
        <tr>
            <td class="box" width="55%">
                <h4>Billed To</h4>
                <b>{{ $bill->patient?->name ?? 'Walk-in' }}</b><br>
                <span class="meta">
                    @if ($bill->patient?->age)Age: {{ $bill->patient->age }}&nbsp;@endif
                    @if ($bill->patient?->gender){{ ucfirst($bill->patient->gender) }}&nbsp;@endif
                    @if ($bill->patient?->phone)&#8226; {{ $bill->patient->phone }}@endif
                </span>
            </td>
            <td class="box" width="45%">
                <h4>Details</h4>
                <table>
                    <tr><td class="meta">Date</td><td>{{ optional($bill->paid_at ?? $bill->created_at)->format('d M Y') }}</td></tr>
                    <tr><td class="meta">Issued by</td><td>{{ $bill->issuer?->name ?? 'System' }}</td></tr>
                    <tr><td class="meta">Status</td>
                        <td>
                            @if($bill->isPaid())
                                <span class="badge badge-paid">PAID</span>
                            @else
                                <span class="badge badge-pending">PENDING</span>
                            @endif
                        </td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th width="8%">#</th>
                <th width="40%">Description</th>
                <th width="52%">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td>Hospital charges</td>
                <td>{{ number_format($bill->amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="totals" style="margin-top:12px; max-width:46%; float:right;">
        <tr><td class="meta">Sub-total</td><td>{{ number_format($bill->amount, 2) }}</td></tr>
        @if ((float) $bill->discount)
            <tr><td class="meta">Discount</td><td>-{{ number_format($bill->discount, 2) }}</td></tr>
        @endif
        @if ((float) $bill->tax)
            <tr><td class="meta">Tax</td><td>{{ number_format($bill->tax, 2) }}</td></tr>
        @endif
        <tr class="grand"><td>Total Due</td><td>{{ number_format($bill->amount + (float) $bill->tax - (float) $bill->discount, 2) }}</td></tr>
    </table>

    <div style="clear:both"></div>

    <div class="status-row">
        @if ($bill->payments->isNotEmpty())
            <div class="box">
                <h4>Payments</h4>
                <table>
                    @foreach ($bill->payments as $payment)
                        <tr>
                            <td class="meta">{{ $payment->created_at->format('d M Y h:i A') }}</td>
                            <td>{{ $payment->mode }} &#8226; {{ number_format($payment->amount, 2) }}</td>
                            <td class="meta">{{ ucfirst($payment->status) }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    </div>

    <table class="sign">
        <tr>
            <td width="34%">Prepared By</td>
            <td width="33%">Checked By</td>
            <td width="33%">Patient / Guardian</td>
        </tr>
    </table>

    <div class="legend">
        This is a computer generated invoice and does not require a stamp or signature to be valid.
    </div>
</body>
</html>