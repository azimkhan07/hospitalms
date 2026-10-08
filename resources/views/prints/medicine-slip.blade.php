<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Medicine Slip</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; margin: 0; width: 48mm; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 2px 0; text-align: left; vertical-align: top; }
        .center { text-align: center; }
        .brand { font-size: 13px; font-weight: bold; letter-spacing: .5px; }
        .sub { font-size: 8px; color: #555; }
        .rule { border-top: 1px dashed #444; margin: 4px 0; }
        .field { font-size: 9px; color: #555; }
        .strong { font-weight: bold; }
        .items th { border-bottom: 1px dotted #444; font-size: 9px; text-transform: uppercase; }
        .total { border-top: 1px solid #222; font-weight: bold; }
        .footer { font-size: 8px; color: #555; margin-top: 6px; }
    </style>
</head>
<body>
    <table class="center">
        <tr><td class="brand">{{ config('app.name', 'HospitalMS') }}</td></tr>
        <tr><td class="sub">Daily Medicine Slip</td></tr>
    </table>
    <div class="rule"></div>

    <table>
        <tr>
            <td class="field">Patient</td>
            <td class="strong">{{ $patient->name }}</td>
        </tr>
        <tr>
            <td class="field">Bed</td>
            <td>{{ $patient->stays->filter(fn ($s) => $s->status === 'active' || blank($s->status))->first()?->bedLabel() ?? '-' }}</td>
        </tr>
        <tr>
            <td class="field">Date</td>
            <td>{{ $day->format('d M Y') }}</td>
        </tr>
    </table>
    <div class="rule"></div>

    <table class="items">
        <thead>
            <tr><th>Medicine</th><th class="center">Qty</th></tr>
        </thead>
        <tbody>
            @forelse ($lines as $line)
                <tr>
                    <td>
                        {{ $line->drug_name ?: $line->medicine }}
                        @if ($line->dosage || $line->frequency)
                            <div class="sub">
                                {{ $line->dosage }}@if($line->dosage && $line->frequency),@endif
                                {{ $line->frequency }}
                                @if($line->duration), {{ $line->duration }} days @endif
                            </div>
                        @endif
                    </td>
                    <td class="center strong">{{ $line->dispensed_qty }}</td>
                </tr>
            @empty
                <tr><td colspan="2" class="sub">No medicines dispensed on this day.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="rule"></div>
    <table class="footer">
        <tr>
            <td>Dispensed by pharmacy</td>
            <td class="center">Time: {{ $day->format('h:i A') }}</td>
        </tr>
    </table>
</body>
</html>