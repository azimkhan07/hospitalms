<div class="content">
    <div class="container">
        <div class="page-title d-flex align-items-center justify-content-between flex-wrap">
            <h3 class="text-info"><i class="fas fa-procedures mr-1"></i> Ward (IPD)</h3>
            <div class="text-muted" style="font-size:12.5px">
                Admitted {{ $activeCount }} &middot; Discharged today {{ $todayDischarges }}
            </div>
        </div>

        @if (session()->has('message'))
            <div class="alert alert-success py-1 px-2">{{ session('message') }}</div>
        @endif
        @if (session()->has('error'))
            <div class="alert alert-danger py-1 px-2">{{ session('error') }}</div>
        @endif

        <div class="row">
            @if ($canAdmit)
                <div class="col-lg-3">
                    <div class="box box-primary">
                        <div class="box-header"><h3 class="box-title"><i class="fas fa-user-plus text-info mr-1"></i> Admit patient</h3></div>
                        <div class="box-body">
                            <div class="form-group">
                                <label>Patient</label>
                                <select class="form-control form-control-sm" wire:model="admitPatientId">
                                    <option value="">Choose patient</option>
                                    @foreach ($patients as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                                    @endforeach
                                </select>
                                @error('admitPatientId') <span class="text-danger" style="font-size:10.5px">{{ $message }}</span> @enderror
                            </div>
                            <div class="form-group">
                                <label>Free bed</label>
                                <select class="form-control form-control-sm" wire:model="admitBedId">
                                    <option value="">Choose bed</option>
                                    @foreach ($freeBeds as $bed)
                                        <option value="{{ $bed['id'] }}">{{ $bed['label'] }}</option>
                                    @endforeach
                                </select>
                                @error('admitBedId') <span class="text-danger" style="font-size:10.5px">{{ $message }}</span> @enderror
                                @if ($freeBeds->isEmpty())
                                    <span class="text-muted" style="font-size:10.5px">No free beds - release or clean one first.</span>
                                @endif
                            </div>
                            <button class="btn btn-sm btn-primary" wire:click="admit">
                                <i class="fas fa-check"></i> Admit
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            <div class="{{ $canAdmit ? 'col-lg-9' : 'col-lg-12' }}">
                <div class="box box-primary">
                    <div class="box-header d-flex align-items-center justify-content-between flex-wrap">
                        <div>
                            <button class="btn btn-sm {{ $tab === 'active' ? 'btn-info' : 'btn-outline-info' }} mr-1"
                                wire:click="$set('tab', 'active')">Admitted</button>
                            <button class="btn btn-sm {{ $tab === 'discharged' ? 'btn-info' : 'btn-outline-info' }} mr-1"
                                wire:click="$set('tab', 'discharged')">Discharged</button>
                        </div>
                        <input type="search" class="form-control form-control-sm" style="max-width:200px"
                            placeholder="Search patient..." wire:model.live.debounce.300ms="search">
                    </div>
                    <div class="box-body">
                        <table class="table table-sm table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Bed</th>
                                    <th>{{ $tab === 'active' ? 'Admitted' : 'Discharged' }}</th>
                                    <th>{{ $tab === 'active' ? 'Latest vitals' : 'Outcome' }}</th>
                                    <th style="width:120px" class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($stays as $row)
                                    <tr>
                                        <td>
                                            {{ $row->patient?->name ?? 'Removed patient' }}
                                            <small class="text-muted d-block">
                                                {{ $row->patient?->age ? $row->patient->age.'y' : '' }}
                                                {{ $row->patient?->gender ? '/ '.$row->patient->gender : '' }}
                                                {{ $row->patient?->bloodgroup ? '/ '.$row->patient->bloodgroup : '' }}
                                            </small>
                                        </td>
                                        <td>{{ $row->bedLabel() }}</td>
                                        <td>{{ optional($row->start_time)->format('d M, h:i A') ?? '-' }}</td>
                                        <td>
                                            @if ($tab === 'active')
                                                @if ($row->latestVital)
                                                    <small>
                                                        BP {{ $row->latestVital->bpLabel() }},
                                                        P {{ $row->latestVital->pulse ?? '-' }},
                                                        SpO2 {{ $row->latestVital->spo2 ?? '-' }}%
                                                    </small>
                                                @else
                                                    <small class="text-muted">No vitals yet</small>
                                                @endif
                                            @else
                                                <span class="badge badge-secondary">
                                                    {{ \App\Models\stay::DISCHARGE_TYPES[$row->discharge_type] ?? ($row->discharge_type ?: '-') }}
                                                </span>
                                                <small class="text-muted d-block">{{ \Illuminate\Support\Str::limit((string) $row->discharge_note, 60) }}</small>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            <button class="btn btn-xs btn-outline-secondary" wire:click="goVitals({{ $row->id }})" title="Vitals">
                                                <i class="fas fa-heartbeat"></i>
                                            </button>
                                            @if ($tab === 'active' && $canDischarge)
                                                <button class="btn btn-xs btn-outline-danger" wire:click="openDischarge({{ $row->id }})" title="Discharge">
                                                    <i class="fas fa-sign-out-alt"></i>
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-3">
                                        {{ $tab === 'active' ? 'Nobody is admitted right now.' : 'No discharges recorded yet.' }}
                                    </td></tr>
                                @endforelse
                            </tbody>
                        </table>

                        @if ($stays->hasPages())
                            <div class="mt-2">{{ $stays->links() }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($dischargeStayId)
        <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.45)">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header py-2">
                        <h5 class="modal-title"><i class="fas fa-sign-out-alt text-danger mr-1"></i> Discharge patient</h5>
                        <button type="button" class="close" wire:click="closeDischarge"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Outcome</label>
                            <select class="form-control form-control-sm" wire:model="dischargeType">
                                @foreach ($dischargeTypes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('dischargeType') <span class="text-danger" style="font-size:10.5px">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group mb-0">
                            <label>Discharge note <span class="text-muted">(advice, transfer to, condition...)</span></label>
                            <textarea rows="3" class="form-control form-control-sm" wire:model="dischargeNote"
                                placeholder="Follow-up advice, receiving hospital..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="closeDischarge">Cancel</button>
                        <button type="button" class="btn btn-sm btn-danger" wire:click="discharge">
                            <i class="fas fa-check"></i> Confirm discharge
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
