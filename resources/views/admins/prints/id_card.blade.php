<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Staff ID Cards</title>
    <style>
        @page { size: 85.6mm 54mm; margin: 0; }

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
            padding: 10px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .toolbar h1 { font-size: 15px; margin: 0; font-weight: 700; }
        .toolbar button {
            background: #0f7fd4;
            color: #fff;
            border: 0;
            border-radius: 4px;
            padding: 7px 14px;
            font-size: 13px;
            cursor: pointer;
        }

        .sheet { padding: 16px; }

        .card-page {
            width: 85.6mm;
            height: 54mm;
            margin: 0 auto 16px;
            background: #fff;
            box-shadow: 0 1px 6px rgba(0,0,0,.2);
            page-break-after: always;
            break-after: page;
            overflow: hidden;
        }
        .card-page:last-child { page-break-after: auto; break-after: auto; }

        .card {
            width: 85.6mm;
            height: 54mm;
            display: flex;
            flex-direction: column;
        }

        /* front */
        .ribbon {
            background: #0b3c66;
            color: #fff;
            text-align: center;
            padding: 2.5mm 2mm 0;
        }
        .ribbon .hospital {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .4px;
            line-height: 1.1;
        }
        .ribbon .gold {
            height: 1px;
            background: #c9a227;
            margin-top: 1.6mm;
        }
        .body {
            flex: 1;
            background: #f6f9fc;
            display: flex;
            align-items: center;
            padding: 2mm 3mm;
            gap: 2.5mm;
        }
        .photo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #0f7fd4;
            background: #fff;
            flex: 0 0 auto;
        }
        .details { flex: 1; min-width: 0; }
        .name {
            font-size: 15px;
            font-weight: 700;
            color: #0b3c66;
            line-height: 1.15;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .chip {
            display: inline-block;
            background: #0f7fd4;
            color: #fff;
            font-size: 8px;
            padding: .5mm 2mm;
            border-radius: 8px;
            margin-top: .8mm;
        }
        .meta {
            font-size: 8px;
            color: #61748a;
            margin-top: 1mm;
        }
        .info {
            font-size: 7.5px;
            color: #61748a;
            margin-top: 1.2mm;
            line-height: 1.35;
        }
        .foot {
            font-size: 6.5px;
            color: #61748a;
            border-top: 1px solid #e3ebf3;
            margin-top: 1.2mm;
            padding-top: .8mm;
        }

        /* back */
        .back {
            background: #f6f9fc;
            flex-direction: row;
            align-items: center;
            padding: 0 4mm;
            gap: 3mm;
        }
        .back .band {
            background: #0b3c66;
            color: #fff;
            width: 6mm;
            align-self: stretch;
            margin: 0 -4mm 0 0;
        }
        .back .back-copy {
            flex: 1;
            text-align: center;
        }
        .back .back-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .3px;
        }
        .back .back-line {
            height: 1px;
            background: #c9a227;
            width: 30mm;
            margin: 2mm auto;
        }
        .back .back-note {
            font-size: 8.5px;
            color: #61748a;
            line-height: 1.4;
        }
        .back .back-employee {
            font-size: 7px;
            color: #61748a;
            margin-top: 2.5mm;
        }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { padding: 0; }
            .card-page {
                margin: 0;
                box-shadow: none;
                page-break-after: always;
                break-after: page;
            }
            .card-page:last-child { page-break-after: auto; break-after: auto; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <h1>Staff ID Cards &middot; {{ count($cards) }} {{ count($cards) === 1 ? 'card' : 'cards' }}</h1>
        <button type="button" onclick="window.print()">Print</button>
    </div>

    <div class="sheet">
        @forelse ($cards as $card)
            <div class="card-page">
                <div class="card">
                    <div class="ribbon">
                        <div class="hospital">{{ $hospital }}</div>
                        <div class="gold"></div>
                    </div>
                    <div class="body">
                        <img class="photo" src="{{ $card['photo'] }}" alt="{{ $card['name'] }}">
                        <div class="details">
                            <div class="name">{{ $card['name'] }}</div>
                            <span class="chip">{{ $card['role'] }}</span>
                            <div class="meta">
                                {{ $card['department'] ?: 'General' }} &middot; {{ $card['staff_code'] }}
                            </div>
                            <div class="info">
                                {{ $card['phone'] ?: 'Phone: -' }}<br>
                                {{ $card['email'] }}
                            </div>
                            <div class="foot">If found return to: {{ $hospital }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-page">
                <div class="card back">
                    <div class="band"></div>
                    <div class="back-copy">
                        <div class="back-title">{{ $hospital }}</div>
                        <div class="back-line"></div>
                        <div class="back-note">
                            If found, please return this card to the reception desk at
                            {{ $hospital }}.
                        </div>
                        <div class="back-employee">
                            {{ $card['name'] }} &middot; {{ $card['staff_code'] }}
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="card-page">
                <div class="card" style="align-items:center;justify-content:center">
                    <div style="font-size:11px;color:#61748a">No staff match this filter.</div>
                </div>
            </div>
        @endforelse
    </div>
</body>
</html>
