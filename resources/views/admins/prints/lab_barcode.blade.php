<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Lab Sample Slip · {{ $report->barcode }}</title>
    <style>
        @page { size: 80mm 120mm; margin: 0; }

        * { box-sizing: border-box; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            background: #e9eef3;
            color: #0b3c66;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #0b3c66;
            color: #fff;
            padding: 8px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .toolbar h1 { font-size: 14px; margin: 0; font-weight: 700; }
        .toolbar button {
            background: #0f7fd4;
            color: #fff;
            border: 0;
            border-radius: 4px;
            padding: 6px 14px;
            font-size: 13px;
            cursor: pointer;
        }

        .sheet { padding: 16px; }

        .slip {
            width: 80mm;
            margin: 0 auto;
            background: #fff;
            box-shadow: 0 1px 6px rgba(0,0,0,.2);
            padding: 6mm 5mm;
        }

        .brand {
            text-align: center;
            border-bottom: 2px solid #0b3c66;
            padding-bottom: 2.5mm;
            margin-bottom: 3mm;
        }
        .brand .logo { max-height: 34px; margin-bottom: 1mm; }
        .brand .name {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .4px;
        }
        .brand .sub { font-size: 8px; color: #61748a; margin-top: .6mm; }

        .barcode {
            text-align: center;
            border: 1.5px dashed #0f7fd4;
            border-radius: 6px;
            padding: 4mm 2mm;
            margin-bottom: 3mm;
        }
        .barcode .code {
            font-family: "Courier New", monospace;
            font-size: 17px;
            font-weight: 700;
            letter-spacing: 1px;
            word-break: break-all;
            line-height: 1.25;
        }
        .barcode .cap { font-size: 8px; color: #61748a; margin-top: 1.5mm; text-transform: uppercase; }

        .row { display: flex; justify-content: space-between; gap: 4mm; margin-bottom: 2mm; }
        .row .k { font-size: 8.5px; color: #61748a; text-transform: uppercase; letter-spacing: .3px; }
        .row .v { font-size: 11px; font-weight: 700; text-align: right; }
        .meta { font-size: 9px; color: #61748a; line-height: 1.5; }

        .foot {
            margin-top: 3mm;
            padding-top: 2mm;
            border-top: 1px solid #e3ebf3;
            font-size: 7.5px;
            color: #61748a;
            text-align: center;
        }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { padding: 0; }
            .slip { box-shadow: none; margin: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <h1>Sample Slip · {{ $report->barcode }}</h1>
        <button type="button" onclick="window.print()">Print</button>
    </div>

    <div class="sheet">
        <div class="slip">
            <div class="brand">
                <img class="logo" src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }}">
                <div class="name">{{ $brand['name'] }}</div>
                <div class="sub">Laboratory Sample Requisition</div>
            </div>

            <div class="barcode">
                <div class="code">{{ $report->barcode }}</div>
                <div class="cap">Scan at the lab counter</div>
            </div>

            <div class="row">
                <span class="k">Patient</span>
                <span class="v">{{ $report->patient?->name ?? 'Removed patient' }}</span>
            </div>
            <div class="row">
                <span class="k">Test</span>
                <span class="v">{{ $report->test?->name ?? 'Test #'.$report->investigation_test_id }}</span>
            </div>
            <div class="meta">
                @if ($report->patient?->age) Age: {{ $report->patient->age }}y &middot; @endif
                @if ($report->patient?->gender) {{ ucfirst($report->patient->gender) }} @endif
                @if ($report->test?->code) &middot; Code: {{ $report->test->code }} @endif
                <br>
                Collected: {{ optional($report->sample_collected_at)->format('d M Y, h:i A') ?? '-' }}
            </div>

            <div class="foot">
                Keep this slip with the sample. Generated {{ now()->format('d M Y, h:i A') }}.
            </div>
        </div>
    </div>
</body>
</html>
