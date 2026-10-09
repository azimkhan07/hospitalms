<div class="hms-queue-monitor" wire:poll.5s>
    <style>
        .hms-queue-monitor { color: #0b3c66; }
        .hms-queue-monitor .hms-qm-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; margin-bottom: 12px; }
        .hms-queue-monitor .hms-qm-head h3 { font-size: 22px; margin: 0; }
        .hms-queue-monitor .hms-qm-clock { color: #61748a; font-size: 12px; }
        .hms-queue-monitor .hms-count-card { border-radius: 10px; padding: 14px 16px; text-align: center; border: 1px solid #e3ebf3; background: #fff; }
        .hms-queue-monitor .hms-count-num { font-size: 40px; font-weight: 700; line-height: 1; }
        .hms-queue-monitor .hms-count-label { font-size: 13px; text-transform: uppercase; letter-spacing: .06em; color: #61748a; margin-top: 4px; }
        .hms-queue-monitor .hms-count-waiting .hms-count-num { color: #0f7fd4; }
        .hms-queue-monitor .hms-count-called { border-color: #ffd79a; background: #fff7e6; }
        .hms-queue-monitor .hms-count-called .hms-count-num { color: #d9822b; }
        .hms-queue-monitor .hms-count-inconsult .hms-count-num { color: #0b3c66; }
        .hms-queue-monitor .hms-next { background: #0b3c66; color: #fff; border-radius: 12px; padding: 16px 18px; }
        .hms-queue-monitor .hms-next-caption { font-size: 12px; text-transform: uppercase; letter-spacing: .12em; color: #9fc6e6; margin-bottom: 4px; }
        .hms-queue-monitor .hms-next-token { font-size: 64px; font-weight: 800; line-height: 1; }
        .hms-queue-monitor .hms-next-name { font-size: 26px; font-weight: 700; }
        .hms-queue-monitor .hms-next-doctor { font-size: 14px; color: #cfe2f3; margin-top: 2px; }
        .hms-queue-monitor .hms-next-chip { display: inline-block; background: rgba(255,255,255,.14); border-radius: 999px; padding: 4px 12px; margin: 0 0 4px 6px; font-size: 13px; }
        .hms-queue-monitor .hms-next-empty { text-align: center; font-size: 22px; font-weight: 600; }
        .hms-queue-monitor .hms-board-token { font-size: 20px; font-weight: 700; color: #0f7fd4; }
        .hms-queue-monitor .hms-section-title { font-size: 13px; text-transform: uppercase; letter-spacing: .05em; color: #61748a; }
    </style>

    <div class="hms-qm-head">
        <h3 class="text-info"><i class="fas fa-tv text-primary mr-1"></i> Reception Queue Monitor</h3>
        <div class="d-flex align-items-center">
            <span class="hms-qm-clock mr-2">{{ now()->format('d M Y, h:i A') }}</span>
            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="refresh">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <div class="row no-gutters mb-2">
        <div class="col-4 pr-1">
            <div class="hms-count-card hms-count-waiting">
                <div class="hms-count-num">{{ $waitingCount }}</div>
                <div class="hms-count-label">Waiting</div>
            </div>
        </div>
        <div class="col-4 px-1">
            <div class="hms-count-card hms-count-called">
                <div class="hms-count-num">{{ $calledCount }}</div>
                <div class="hms-count-label">Called</div>
            </div>
        </div>
        <div class="col-4 pl-1">
            <div class="hms-count-card hms-count-inconsult">
                <div class="hms-count-num">{{ $inConsultCount }}</div>
                <div class="hms-count-label">In Consult</div>
            </div>
        </div>
    </div>

    @if ($nextToken)
        <div class="hms-next mb-2">
            <div class="hms-next-caption">Up next</div>
            <div class="d-flex align-items-center justify-content-between flex-wrap">
                <div class="d-flex align-items-center">
                    <div class="hms-next-token">#{{ $nextToken->token ?? '-' }}</div>
                    <div class="ml-3">
                        <div class="hms-next-name">{{ $nextToken->patient?->name ?? 'Unknown patient' }}</div>
                        <div class="hms-next-doctor">
                            <i class="fas fa-user-md"></i> {{ $nextToken->doctor?->employ?->name ?? 'Unassigned doctor' }}
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    @foreach ($upNext->skip(1) as $nxt)
                        <span class="hms-next-chip">#{{ $nxt->token ?? '-' }} {{ $nxt->patient?->name ?? '-' }}</span>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        <div class="hms-next hms-next-empty mb-2">Queue is clear.</div>
    @endif

    <div class="box box-primary mb-2">
        <div class="box-header py-2">
            <h3 class="box-title hms-section-title"><i class="fas fa-user-clock text-info mr-1"></i> Waiting list</h3>
        </div>
        <div class="box-body pt-1">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th style="width:90px">Token</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($waiting as $row)
                        <tr>
                            <td class="hms-board-token">#{{ $row->token ?? '-' }}</td>
                            <td>{{ $row->patient?->name ?? 'Removed patient' }}</td>
                            <td>{{ $row->doctor?->employ?->name ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-3">Nobody waiting.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="row no-gutters">
        <div class="col-md-6 pr-1">
            <div class="box box-warning mb-0">
                <div class="box-header py-2">
                    <h3 class="box-title hms-section-title">
                        <i class="fas fa-bell text-warning mr-1"></i> Called ({{ $calledCount }})
                    </h3>
                </div>
                <div class="box-body pt-1">
                    @forelse ($called as $row)
                        <div class="mb-1" style="font-size:13px">
                            <b class="hms-board-token">#{{ $row->token ?? '-' }}</b>
                            {{ $row->patient?->name ?? '-' }}
                            <span class="text-muted">&rarr; {{ $row->doctor?->employ?->name ?? '-' }}</span>
                        </div>
                    @empty
                        <span class="text-muted" style="font-size:12px">None called.</span>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-md-6 pl-1">
            <div class="box box-primary mb-0">
                <div class="box-header py-2">
                    <h3 class="box-title hms-section-title">
                        <i class="fas fa-stethoscope text-primary mr-1"></i> In Consult ({{ $inConsultCount }})
                    </h3>
                </div>
                <div class="box-body pt-1">
                    @forelse ($inConsult as $row)
                        <div class="mb-1" style="font-size:13px">
                            <b class="hms-board-token">#{{ $row->token ?? '-' }}</b>
                            {{ $row->patient?->name ?? '-' }}
                            <span class="text-muted">&rarr; {{ $row->doctor?->employ?->name ?? '-' }}</span>
                        </div>
                    @empty
                        <span class="text-muted" style="font-size:12px">None in consult.</span>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
