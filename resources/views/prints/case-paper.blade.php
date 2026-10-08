<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Case Paper - {{ $patient->name }}</title>
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
        .items th { background: #f2f8fd; color: #0b3c66; font-size: 10px; border-bottom: 1px solid #e3ebf3; }
        .items td { border-bottom: 1px solid #eef3f9; font-size: 11px; }
        .field { color: #61748a; font-size: 10px; width: 30%; }
        .value { font-weight: bold; }
        .patch { border: 1px dashed #c9d6e3; border-radius: 4px; padding: 6px 10px; margin-bottom: 12px; font-size: 10px; color: #61748a; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td class="brand">
                {{ config('app.name', 'HospitalMS') }}
                <small>{{ config('hms.address', '') }}</small>
            </td>
            <td class="doc-title">CASE PAPER</td>
        </tr>
    </table>

    <div class="box">
        <h4>Patient</h4>
        <table>
            <tr>
                <td class="field">Name</td><td class="value">{{ $patient->name }}</td>
                <td class="field">Age / Gender</td>
                <td class="value">{{ $patient->age ?? '-' }} / {{ ucfirst($patient->gender ?? '-') }}</td>
            </tr>
            <tr>
                <td class="field">Phone</td><td class="value">{{ $patient->phone }}</td>
                <td class="field">Blood Group</td><td class="value">{{ $patient->bloodgroup ?? '-' }}</td>
            </tr>
            <tr>
                <td class="field">Address</td>
                <td colspan="3" class="value">{{ $patient->address ?? '-' }}</td>
            </tr>
        </table>
    </div>

    @if ($patient->stays->isNotEmpty())
        <div class="box">
            <h4>Admissions</h4>
            <table class="items">
                <thead><tr><th>From</th><th>To</th><th>Room / Bed</th></tr></thead>
                <tbody>
                    @foreach ($patient->stays->take(4) as $stay)
                        <tr>
                            <td>{{ $stay->start_time?->format('d M Y h:i A') ?? '-' }}</td>
                            <td>{{ $stay->end_time?->format('d M Y h:i A') ?? 'Ongoing' }}</td>
                            <td>{{ $stay->bedLabel() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($patient->prescriptions->isNotEmpty())
        <div class="box">
            <h4>Prescription History</h4>
            @foreach ($patient->prescriptions->take(4) as $prescription)
                <table class="items" style="margin-bottom:6px;">
                    <tr>
                        <th>Dr. {{ $prescription->doctor?->name ?? '-' }}</th>
                        <th class="meta">{{ $prescription->issued_at?->format('d M Y') ?? '-' }}</th>
                    </tr>
                    @if ($prescription->notes)
                        <tr><td colspan="2" class="meta">Notes: {{ $prescription->notes }}</td></tr>
                    @endif
                    @foreach ($prescription->items as $item)
                        <tr>
                            <td>{{ $item->medicine }}@if($item->dosage) <span class="meta">{{ $item->dosage }}</span>@endif</td>
                            <td class="meta">
                                {{ $item->frequency }}
                                @if($item->duration) for {{ $item->duration }} days @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            @endforeach
        </div>
    @endif

    <div class="patch">
        Issued on {{ today()->format('d M Y') }}. Hand this paper to the consulting doctor on your next visit.
    </div>
</body>
</html>