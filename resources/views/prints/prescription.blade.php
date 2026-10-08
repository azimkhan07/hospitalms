<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Prescription</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #24303c; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 5px 7px; text-align: left; vertical-align: top; }
        .header { border-bottom: 2px solid #0f7fd4; padding-bottom: 8px; margin-bottom: 12px; }
        .brand { font-size: 18px; font-weight: bold; color: #0b3c66; }
        .brand small { display: block; font-size: 10px; color: #61748a; font-weight: normal; }
        .doc-title { font-size: 14px; font-weight: bold; color: #0f7fd4; text-align: right; }
        .meta { font-size: 10px; color: #61748a; }
        .box { border: 1px solid #e3ebf3; border-radius: 4px; padding: 8px 10px; margin-bottom: 12px; }
        .box h4 { margin: 0 0 6px; font-size: 11px; color: #0b3c66; text-transform: uppercase; letter-spacing: .5px; }
        .field { color: #61748a; font-size: 10px; width: 34%; }
        .value { font-weight: bold; }
        .items th { background: #f2f8fd; color: #0b3c66; font-size: 10px; border-bottom: 1px solid #e3ebf3; }
        .items td { border-bottom: 1px solid #eef3f9; font-size: 11px; }
        .rx { font-family: DejaVu Sans, serif; font-size: 15px; }
        .sign { margin-top: 26px; }
        .sign td { text-align: right; border-top: 1px solid #e3ebf3; padding-top: 40px; font-size: 10px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td class="brand">
                {{ config('app.name', 'HospitalMS') }}
                <small>{{ config('hms.address', '') }}</small>
            </td>
            <td class="doc-title">PRESCRIPTION</td>
        </tr>
    </table>

    <div class="box">
        <h4>Prescribed To</h4>
        <table>
            <tr>
                <td class="field">Patient</td><td class="value">{{ $prescription->patient?->name ?? '-' }}</td>
                <td class="field">Age / Gender</td>
                <td class="value">{{ $prescription->patient?->age ?? '-' }} / {{ ucfirst($prescription->patient?->gender ?? '-') }}</td>
            </tr>
            <tr>
                <td class="field">Doctor</td><td class="value">Dr. {{ $prescription->doctor?->name ?? '-' }}</td>
                <td class="field">Date</td>
                <td class="value">{{ $prescription->issued_at?->format('d M Y') ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="40%">Medicine</th>
                <th width="20%">Dosage</th>
                <th width="15%">Frequency</th>
                <th width="20%">Duration</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($prescription->items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->medicine }}</td>
                    <td>{{ $item->dosage }}</td>
                    <td>{{ $item->frequency }}</td>
                    <td>{{ $item->duration ? $item->duration.' days' : '-' }}</td>
                </tr>
                @if ($item->note)
                    <tr><td colspan="5" class="meta">&#8226; {{ $item->note }}</td></tr>
                @endif
            @empty
                <tr><td colspan="5" class="meta">No medicines listed.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($prescription->notes)
        <div class="box" style="margin-top:10px;">
            <h4>Notes</h4>
            <p style="margin:0;">{{ $prescription->notes }}</p>
        </div>
    @endif

    <table class="sign">
        <tr><td style="text-align:left;">Rx</td><td>Dr. {{ $prescription->doctor?->name ?? '' }}</td></tr>
    </table>
</body>
</html>