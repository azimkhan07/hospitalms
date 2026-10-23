<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Prescription</title>
    <style>
        * { box-sizing: border-box; }
        @page { margin: 24px 30px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #24303c; margin: 0; }

        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; }

        .top-rule { height: 4px; background: #0f7fd4; border-radius: 2px; margin-bottom: 10px; }

        .head td { vertical-align: middle; }
        .logo { width: 74px; padding-right: 10px; }
        .logo img { max-width: 68px; max-height: 58px; }
        .clinic-name { font-size: 17px; font-weight: bold; color: #0b3c66; line-height: 1.2; }
        .clinic-meta { font-size: 9px; color: #61748a; line-height: 1.5; }
        .doctor { text-align: right; }
        .doctor .name { font-size: 12px; font-weight: bold; color: #0b3c66; }
        .doctor .role { font-size: 9px; color: #61748a; }

        .title-bar { margin: 12px 0 10px; border-top: 1px solid #e3ebf3; border-bottom: 1px solid #e3ebf3; }
        .title-bar td { padding: 6px 0; }
        .doc-title { font-size: 12px; font-weight: bold; color: #0f7fd4; letter-spacing: 1.5px; text-transform: uppercase; }
        .rx-no { text-align: right; font-size: 9px; color: #61748a; }

        .patient { background: #f2f8fd; border: 1px solid #dceaf6; border-radius: 5px; padding: 8px 10px; margin-bottom: 12px; }
        .patient td { padding: 1px 0; }
        .field { color: #61748a; font-size: 8.5px; text-transform: uppercase; letter-spacing: .3px; }
        .value { font-weight: bold; font-size: 10.5px; color: #24303c; }

        .rx-body { border: 1px solid #e3ebf3; border-radius: 5px; min-height: 250px; padding: 10px 12px; }
        .rx-symbol { font-family: "DejaVu Serif", serif; font-size: 30px; font-weight: bold; color: #0f7fd4; line-height: 1; }
        .rx-caption { font-size: 8.5px; color: #61748a; margin-bottom: 6px; }

        .items td { padding: 7px 4px; border-bottom: 1px dotted #cfdde9; }
        .items .num { width: 20px; color: #9aabba; font-size: 9px; }
        .items .med { width: 46%; font-weight: bold; font-size: 11px; color: #1b2b3c; }
        .items .dose { width: 46%; color: #3f5266; font-size: 10px; }
        .items .dose .timing { color: #61748a; }
        .items tr.blank td { height: 24px; }

        .notes { margin-top: 10px; border: 1px solid #e3ebf3; border-radius: 5px; padding: 7px 10px; }
        .notes .field { margin-bottom: 3px; }
        .notes p { margin: 0; font-size: 10px; }

        .sign { margin-top: 14px; }
        .sign .line { border-top: 1px solid #8fa4b8; padding-top: 5px; font-size: 9px; color: #61748a; text-align: right; }
        .sign .name { font-size: 10.5px; font-weight: bold; color: #24303c; }

        .foot { margin-top: 8px; font-size: 8px; color: #9aabba; text-align: center; }
    </style>
</head>
<body>
    @php
        $tenant = auth()->user()?->tenant;
        $brand = hms_tenant_brand();

        $logoRelative = $tenant?->logo;
        $logoFile = $logoRelative
            ? storage_path('app/public/'.ltrim($logoRelative, '/'))
            : public_path('storage/default.png');

        if (! is_file($logoFile)) {
            $logoFile = public_path('storage/default.png');
        }

        $items = $prescription->items;
        $blankLines = max(0, 8 - $items->count());
    @endphp

    <div class="top-rule"></div>

    <table class="head">
        <tr>
            <td class="logo">
                <img src="{{ $logoFile }}" alt="Logo">
            </td>
            <td>
                <div class="clinic-name">{{ $brand['name'] }}</div>
                <div class="clinic-meta">
                    @if ($tenant?->address){{ $tenant->address }}@endif
                    @if ($tenant?->city){{ $tenant->city ? ', '.$tenant->city : '' }}@endif
                    @if ($tenant?->phone)<br>{{ $tenant->phone }}@endif
                    @if ($tenant?->email)&nbsp;&#8226;&nbsp;{{ $tenant->email }}@endif
                </div>
            </td>
            <td class="doctor">
                <div class="name">Dr. {{ $prescription->doctor?->name ?? '—' }}</div>
                <div class="role">Consultant Physician</div>
                @if ($tenant?->working_hours)
                    <div class="role">{{ $tenant->working_hours }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="title-bar">
        <tr>
            <td class="doc-title">Prescription</td>
            <td class="rx-no">
                Rx #{{ str_pad((string) $prescription->id, 5, '0', STR_PAD_LEFT) }}
                &nbsp;&#8226;&nbsp;
                {{ $prescription->issued_at?->format('d M Y') ?? now()->format('d M Y') }}
            </td>
        </tr>
    </table>

    <table class="patient">
        <tr>
            <td width="42%">
                <div class="field">Patient name</div>
                <div class="value">{{ $prescription->patient?->name ?? '-' }}</div>
            </td>
            <td width="18%">
                <div class="field">Age</div>
                <div class="value">{{ $prescription->patient?->age ?? '-' }}</div>
            </td>
            <td width="20%">
                <div class="field">Gender</div>
                <div class="value">{{ $prescription->patient?->gender ? ucfirst($prescription->patient->gender) : '-' }}</div>
            </td>
            <td width="20%">
                <div class="field">Date</div>
                <div class="value">{{ $prescription->issued_at?->format('d M Y') ?? now()->format('d M Y') }}</div>
            </td>
        </tr>
    </table>

    <div class="rx-body">
        <table>
            <tr>
                <td width="42">
                    <div class="rx-symbol">&#8478;</div>
                </td>
                <td class="rx-caption">Medicine &amp; dosage timing</td>
            </tr>
        </table>

        <table class="items">
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td class="num">{{ $loop->iteration }}.</td>
                        <td class="med">{{ $item->medicine }}</td>
                        <td class="dose">
                            @php
                                $timing = collect([
                                    $item->dosage,
                                    $item->frequency,
                                    $item->duration ? $item->duration.' days' : null,
                                ])->filter()->implode(' · ');
                            @endphp
                            {{ $timing !== '' ? $timing : 'As directed' }}
                            @if ($item->note)
                                <div class="timing">{{ $item->note }}</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr class="blank">
                        <td class="num"></td>
                        <td class="med"></td>
                        <td class="dose"></td>
                    </tr>
                @endforelse

                @for ($i = 0; $i < $blankLines; $i++)
                    <tr class="blank">
                        <td class="num"></td>
                        <td class="med"></td>
                        <td class="dose"></td>
                    </tr>
                @endfor
            </tbody>
        </table>
    </div>

    @if ($prescription->notes)
        <div class="notes">
            <div class="field">Advice / Notes</div>
            <p>{{ $prescription->notes }}</p>
        </div>
    @endif

    <table class="sign">
        <tr>
            <td width="60%"></td>
            <td width="40%">
                <div class="line">
                    <div class="name">Dr. {{ $prescription->doctor?->name ?? '' }}</div>
                    Signature
                </div>
            </td>
        </tr>
    </table>

    <div class="foot">
        This prescription is computer generated. Please take medicines exactly as advised.
    </div>
</body>
</html>
