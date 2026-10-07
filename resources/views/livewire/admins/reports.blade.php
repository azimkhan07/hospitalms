<div>
    <div class="row mb-2 d-flex align-items-center">
        <div class="col-md-7">
            <h4 class="mb-1"><i class="fas fa-chart-line text-info mr-1"></i> Reports &amp; Analytics
                <span class="badge badge-light text-muted">{{ $rangeLabel }}</span></h4>
            <div class="d-flex flex-wrap align-items-center" style="gap:.3rem">
                @foreach (['today' => 'Today', 'week' => 'This week', 'month' => 'This month', 'all' => 'All time'] as $key => $label)
                    <button type="button"
                        class="btn btn-xs {{ $preset === $key ? 'btn-primary' : 'btn-outline-secondary' }}"
                        wire:click="setRange('{{ $key }}')">{{ $label }}</button>
                @endforeach
                <input type="date" class="form-control form-control-sm d-inline-block hms-report-date" wire:model="from">
                <input type="date" class="form-control form-control-sm d-inline-block hms-report-date" wire:model="to">
            </div>
        </div>
        <div class="col-md-5 text-md-right">
            <a href="{{ route('admin_reports_download', ['from' => $from, 'to' => $to]) }}"
                class="btn btn-sm btn-outline-primary">
                <i class="fas fa-file-csv"></i> Export CSV
            </a>
        </div>
    </div>

    <div class="row">
        @foreach ([
            ['label' => 'New patients', 'value' => $r['kpIs']['new_patients'], 'icon' => 'fa-user-plus', 'color' => '#0f7fd4'],
            ['label' => 'Appointments', 'value' => $r['kpIs']['appointments'], 'icon' => 'fa-calendar-check', 'color' => '#157fda'],
            ['label' => 'Admitted', 'value' => $r['kpIs']['admitted'], 'icon' => 'fa-procedures', 'color' => '#f39c12'],
            ['label' => 'Billed', 'value' => number_format($r['kpIs']['billed'], 0), 'icon' => 'fa-file-invoice-dollar', 'color' => '#2f9e6f'],
            ['label' => 'Collected', 'value' => number_format($r['kpIs']['collected'], 0), 'icon' => 'fa-hand-holding-usd', 'color' => '#2f9e6f'],
            ['label' => 'Outstanding', 'value' => number_format($r['kpIs']['outstanding'], 0), 'icon' => 'fa-hourglass-half', 'color' => '#d9534f'],
        ] as $kpi)
            <div class="col-lg-2 col-md-4 col-6 mb-2">
                <div class="box box-primary hms-report-kpi" style="margin-bottom:0">
                    <div class="box-body d-flex align-items-center" style="padding:.55rem .65rem">
                        <div class="hms-kpi-icon" style="background:{{ $kpi['color'] }}">
                            <i class="fas {{ $kpi['icon'] }}"></i>
                        </div>
                        <div class="ml-2">
                            <div class="hms-kpi-value">{{ $kpi['value'] }}</div>
                            <div class="hms-kpi-label">{{ $kpi['label'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-xl-4 col-md-6">
            <div class="box box-primary">
                <div class="box-header"><h3 class="box-title"><i class="fas fa-users text-info mr-1"></i> Patients</h3></div>
                <div class="box-body" style="font-size:12px">
                    <div class="d-flex justify-content-between"><span>Total patients</span><strong>{{ $r['patients']['total'] }}</strong></div>
                    <div class="d-flex justify-content-between"><span>Registered in range</span><strong>{{ $r['patients']['new'] }}</strong></div>
                    <div class="d-flex justify-content-between"><span>Male</span><strong>{{ $r['patients']['male'] }}</strong></div>
                    <div class="d-flex justify-content-between"><span>Female</span><strong>{{ $r['patients']['female'] }}</strong></div>
                    <div class="mt-2 pt-2 border-top">
                        <div class="d-flex justify-content-between"><span class="text-muted">Admitted</span><strong>{{ $r['patients']['admitted'] }}</strong></div>
                        <div class="d-flex justify-content-between"><span class="text-muted">Discharged</span><strong>{{ $r['patients']['discharged'] }}</strong></div>
                        <div class="d-flex justify-content-between"><span class="text-muted">Pending admission</span><strong>{{ $r['patients']['pending'] }}</strong></div>
                    </div>
                    @if ($r['patients']['total'])
                        <div class="progress mt-2" style="height:8px">
                            @php $adw = $r['patients']['total'] ? round($r['patients']['admitted'] / max(1, $r['patients']['total']) * 100) : 0; @endphp
                            <div class="progress-bar bg-info" style="width:{{ $adw }}%"></div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="box box-primary">
                <div class="box-header"><h3 class="box-title"><i class="fas fa-procedures text-info mr-1"></i> OPD / IPD Census</h3></div>
                <div class="box-body" style="font-size:12px">
                    <div class="d-flex justify-content-between"><span>Current in-patients</span><strong>{{ $r['patients']['admitted'] }}</strong></div>
                    <div class="d-flex justify-content-between"><span>Active stays</span><strong>{{ $r['ipd']['current_stays'] }}</strong></div>
                    <div class="d-flex justify-content-between"><span>Admissions in range</span><strong>{{ $r['ipd']['admissions'] }}</strong></div>
                    <hr>
                    <div class="text-muted mb-1">Appointments by status</div>
                    @forelse ($r['appointments'] as $status => $count)
                        <div class="d-flex justify-content-between">
                            <span class="text-capitalize">{{ $status }}</span><strong>{{ $count }}</strong>
                        </div>
                    @empty
                        <div class="text-muted">No appointments in range.</div>
                    @endforelse
                </div>
            </div>
            <div class="box box-primary">
                <div class="box-header"><h3 class="box-title"><i class="fas fa-bed text-info mr-1"></i> Bed Occupancy</h3></div>
                <div class="box-body" style="font-size:12px">
                    @php $occup = $r['beds']['total'] ? round($r['beds']['alloted'] / $r['beds']['total'] * 100) : 0; @endphp
                    <div class="d-flex justify-content-between"><span>Alloted beds</span><strong>{{ $r['beds']['alloted'] }} / {{ $r['beds']['total'] }}</strong></div>
                    <div class="progress mb-2" style="height:10px">
                        <div class="progress-bar bg-success" style="width:{{ $occup }}%"></div>
                    </div>
                    <div class="row text-center">
                        <div class="col-3"><div class="small text-muted">Free</div><strong>{{ $r['beds']['available'] }}</strong></div>
                        <div class="col-3"><div class="small text-muted">Cleaning</div><strong>{{ $r['beds']['cleaning'] }}</strong></div>
                        <div class="col-3"><div class="small text-muted">Reserved</div><strong>{{ $r['beds']['reserved'] }}</strong></div>
                        <div class="col-3"><div class="small text-muted">Maint.</div><strong>{{ $r['beds']['maintenance'] }}</strong></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="box box-primary">
                <div class="box-header"><h3 class="box-title"><i class="fas fa-file-invoice-dollar text-info mr-1"></i> Revenue (last 6 months)</h3></div>
                <div class="box-body">
                    @foreach ($r['revenue']['monthly'] as $m)
                        <div class="hms-bar-row">
                            <span class="hms-bar-label">{{ $m['label'] }}</span>
                            <div class="hms-bar-track">
                                <div class="hms-bar hms-bar-billed" style="width:{{ round($m['billed'] / $maxMonthly * 100) }}%"></div>
                                <div class="hms-bar hms-bar-collected" style="width:{{ round($m['collected'] / $maxMonthly * 100) }}%"></div>
                            </div>
                            <span class="hms-bar-value">
                                <i class="fas fa-file-invoice text-muted"></i> {{ number_format($m['billed'], 0) }}
                                <i class="fas fa-hand-holding-usd text-muted ml-1"></i> {{ number_format($m['collected'], 0) }}
                            </span>
                        </div>
                    @endforeach
                    <div class="border-top pt-2 mt-2 text-muted" style="font-size:11.5px">
                        <span><i class="hms-dot hms-dot-billed"></i> Billed</span>
                        <span class="ml-2"><i class="hms-dot hms-dot-collected"></i> Collected</span>
                    </div>
                    <div class="d-flex justify-content-between mt-2">
                        <span>Tax billed</span><strong>Rs {{ number_format($r['revenue']['tax'], 2) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Collected in range</span><strong>Rs {{ number_format($r['revenue']['collected'], 2) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Outstanding receivables</span><strong class="text-danger">Rs {{ number_format($r['revenue']['outstanding'], 2) }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        @if (count($r['doctor_performance']))
            <div class="col-xl-6">
                <div class="box box-primary">
                    <div class="box-header"><h3 class="box-title"><i class="fas fa-user-md text-info mr-1"></i> Doctor Performance</h3></div>
                    <div class="box-body p-0">
                        @foreach ($r['doctor_performance'] as $i => $d)
                            <div class="hms-perf-row">
                                <span class="hms-perf-rank">{{ $i + 1 }}</span>
                                <span class="hms-perf-name">{{ $d['doctor'] }}</span>
                                <div class="hms-perf-track">
                                    <div class="hms-perf-fill" style="width:{{ round($d['visits'] / max(1, $r['doctor_performance'][0]['visits']) * 100) }}%"></div>
                                </div>
                                <span class="hms-perf-count">{{ $d['visits'] }} visits</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div class="col-xl-3 col-md-6">
            <div class="box box-primary">
                <div class="box-header"><h3 class="box-title"><i class="fas fa-pills text-info mr-1"></i> Pharmacy</h3></div>
                <div class="box-body" style="font-size:12px">
                    <div class="d-flex justify-content-between"><span>Medicines</span><strong>{{ $r['pharmacy']['items'] }}</strong></div>
                    <div class="d-flex justify-content-between"><span class="text-danger">Low stock</span><strong class="text-danger">{{ $r['pharmacy']['low_stock'] }}</strong></div>
                    <div class="d-flex justify-content-between"><span>Expiring in 90 days</span><strong>{{ $r['pharmacy']['expiring_90d'] }}</strong></div>
                    <div class="d-flex justify-content-between"><span>Prescriptions in range</span><strong>{{ $r['pharmacy']['prescriptions'] }}</strong></div>
                </div>
            </div>
            <div class="box box-primary">
                <div class="box-header"><h3 class="box-title"><i class="fas fa-flask text-info mr-1"></i> Diagnostics</h3></div>
                <div class="box-body" style="font-size:12px">
                    <div class="d-flex justify-content-between"><span>Rate-card tests</span><strong>{{ $r['lab']['tests'] }}</strong></div>
                    <div class="d-flex justify-content-between"><span>Machines working</span><strong>{{ $r['lab']['machines_working'] }} / {{ $r['lab']['machines_total'] }}</strong></div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="box box-primary">
                <div class="box-header"><h3 class="box-title"><i class="fas fa-money-bill text-info mr-1"></i> Due List</h3></div>
                <div class="box-body p-0">
                    @forelse ($r['due_list'] as $d)
                        <div class="hms-due-row">
                            <span class="hms-due-main">
                                <strong>{{ $d['invoice'] }}</strong>
                                <span class="text-muted">{{ $d['patient'] }}</span>
                            </span>
                            <span class="text-danger">Rs {{ number_format($d['due'], 2) }}</span>
                        </div>
                    @empty
                        <div class="p-3 text-muted" style="font-size:12px">No outstanding bills. </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <style>
        .hms-report-date { width: 132px; display: inline-block; }
        .hms-report-kpi { margin-bottom: 0; min-height: 62px; }
        .hms-kpi-icon { width: 34px; height: 34px; min-width: 34px; border-radius: 6px; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 15px; }
        .hms-kpi-value { font-size: 15px; font-weight: 700; color: #2b3b52; line-height: 1.1; }
        .hms-kpi-label { font-size: 10.5px; color: #75879e; text-transform: uppercase; letter-spacing: .2px; }
        .hms-bar-row { display: flex; align-items: center; gap: .4rem; font-size: 11px; margin-bottom: .35rem; }
        .hms-bar-label { width: 2.2rem; color: #6b7c93; }
        .hms-bar-track { position: relative; flex: 1; height: 12px; background: #f1f5fa; border-radius: 2px; overflow: hidden; }
        .hms-bar { position: absolute; top: 0; bottom: 0; }
        .hms-bar-billed { background: #157fda; }
        .hms-bar-collected { background: #2f9e6f; left: 0; }
        .hms-bar-value { width: 170px; text-align: right; white-space: nowrap; }
        .hms-dot { display: inline-block; width: 9px; height: 9px; border-radius: 2px; margin-right: .2rem; }
        .hms-dot-billed { background: #157fda; }
        .hms-dot-collected { background: #2f9e6f; }
        .hms-perf-row, .hms-due-row { display: flex; align-items: center; gap: .5rem; font-size: 12px; padding: .45rem .75rem; border-bottom: 1px solid #f0f3f8; }
        .hms-perf-rank { width: 20px; height: 20px; line-height: 20px; text-align: center; border-radius: 50%; background: #e2eefc; color: #0f7fd4; font-weight: 700; font-size: 11px; }
        .hms-perf-name { width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .hms-perf-track { flex: 1; height: 8px; background: #f1f5fa; border-radius: 2px; overflow: hidden; }
        .hms-perf-fill { height: 100%; background: #5cb85c; }
        .hms-perf-count { width: 90px; text-align: right; color: #6b7c93; }
        .hms-due-row { justify-content: space-between; }
        .hms-due-main { display: flex; flex-direction: column; }
        .hms-due-main strong { font-size: 12px; }
        .hms-due-main .text-muted { font-size: 10.5px; }
    </style>
</div>