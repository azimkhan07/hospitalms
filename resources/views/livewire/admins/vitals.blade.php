<div class="content">
    <div class="container">
        <div class="page-title">
            <h3 class="text-info"><i class="fas fa-heartbeat mr-1"></i> Vitals</h3>
        </div>

        @if (session()->has('message'))
            <div class="alert alert-success py-1 px-2">{{ session('message') }}</div>
        @endif

        <div class="row">
            <div class="col-lg-5">
                <div class="box box-primary">
                    <div class="box-header"><h3 class="box-title"><i class="fas fa-pencil-alt text-info mr-1"></i> Record observation</h3></div>
                    <div class="box-body">
                        <div class="form-group">
                            <label>Patient</label>
                            <select class="form-control form-control-sm" wire:model.live="patientId">
                                <option value="">Choose patient</option>
                                @foreach ($patients as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                                @endforeach
                            </select>
                            @error('patientId') <span class="text-danger" style="font-size:10.5px">{{ $message }}</span> @enderror
                        </div>

                        @if ($appointment !== '' || $stay !== '')
                            <div class="mb-2">
                                @if ($appointment !== '')
                                    <span class="badge badge-info">OPD visit #{{ $appointment }}</span>
                                @endif
                                @if ($stay !== '')
                                    <span class="badge badge-warning">IPD stay #{{ $stay }}</span>
                                @endif
                            </div>
                        @endif

                        <div class="form-row">
                            <div class="form-group col-4">
                                <label>BP sys</label>
                                <input type="number" class="form-control form-control-sm" wire:model="bpSystolic" placeholder="120">
                            </div>
                            <div class="form-group col-4">
                                <label>BP dia</label>
                                <input type="number" class="form-control form-control-sm" wire:model="bpDiastolic" placeholder="80">
                            </div>
                            <div class="form-group col-4">
                                <label>Pulse</label>
                                <input type="number" class="form-control form-control-sm" wire:model="pulse" placeholder="72">
                            </div>
                        </div>
                        @error('pulse') <div class="text-danger mb-1" style="font-size:10.5px">{{ $message }}</div> @enderror
                        @error('bpSystolic') <div class="text-danger mb-1" style="font-size:10.5px">{{ $message }}</div> @enderror
                        @error('bpDiastolic') <div class="text-danger mb-1" style="font-size:10.5px">{{ $message }}</div> @enderror

                        <div class="form-row">
                            <div class="form-group col-4">
                                <label>Temp &deg;C</label>
                                <input type="number" step="0.1" class="form-control form-control-sm" wire:model="temperature" placeholder="36.8">
                            </div>
                            <div class="form-group col-4">
                                <label>SpO2 %</label>
                                <input type="number" class="form-control form-control-sm" wire:model="spo2" placeholder="98">
                            </div>
                            <div class="form-group col-4">
                                <label>Weight kg</label>
                                <input type="number" step="0.1" class="form-control form-control-sm" wire:model="weight" placeholder="70">
                            </div>
                        </div>
                        @error('temperature') <div class="text-danger mb-1" style="font-size:10.5px">{{ $message }}</div> @enderror
                        @error('spo2') <div class="text-danger mb-1" style="font-size:10.5px">{{ $message }}</div> @enderror
                        @error('weight') <div class="text-danger mb-1" style="font-size:10.5px">{{ $message }}</div> @enderror

                        <div class="form-row">
                            <div class="form-group col-4">
                                <label>Height cm</label>
                                <input type="number" step="0.1" class="form-control form-control-sm" wire:model="height" placeholder="170">
                            </div>
                            <div class="form-group col-8">
                                <label>Note</label>
                                <input type="text" class="form-control form-control-sm" wire:model="note" placeholder="Condition, position...">
                            </div>
                        </div>
                        @error('height') <div class="text-danger mb-1" style="font-size:10.5px">{{ $message }}</div> @enderror

                        <button class="btn btn-sm btn-primary" wire:click="save" wire:loading.attr="disabled">
                            <i class="fas fa-save"></i> Save vitals
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="box box-primary">
                    <div class="box-header"><h3 class="box-title"><i class="fas fa-clinic-medical text-info mr-1"></i> Recent readings{{ $patient ? ' - '.$patient->name : '' }}</h3></div>
                    <div class="box-body">
                        <table class="table table-sm table-bordered mb-0" style="font-size:12.5px">
                            <thead>
                                <tr><th>When</th><th>BP</th><th>Pulse</th><th>Temp</th><th>SpO2</th><th>By</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($recent as $v)
                                    <tr>
                                        <td>{{ optional($v->taken_at)->format('d M, h:i A') }}</td>
                                        <td>{{ $v->bpLabel() }}</td>
                                        <td>{{ $v->pulse ?? '-' }}</td>
                                        <td>{{ $v->temperature ?? '-' }}</td>
                                        <td>{{ $v->spo2 ?? '-' }}</td>
                                        <td>{{ $v->recorder?->name ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-3">Pick a patient to see past readings.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="box box-primary">
                    <div class="box-header"><h3 class="box-title"><i class="fas fa-list text-info mr-1"></i> Today's queue &amp; ward</h3></div>
                    <div class="box-body">
                        @foreach ($queue as $appt)
                            <button class="btn btn-xs btn-outline-info mr-1 mb-1" wire:click="useAppointment({{ $appt->id }})">
                                {{ optional($appt->intime)->format('h:i A') }}
                                - {{ $appt->patient?->name ?? '#' }}
                                <span class="badge badge-secondary">{{ str_replace('_', ' ', $appt->status) }}</span>
                            </button>
                        @endforeach

                        @foreach ($wardList as $row)
                            <button class="btn btn-xs btn-outline-warning mr-1 mb-1" wire:click="useStay({{ $row->id }})">
                                <i class="fas fa-bed"></i>
                                {{ $row->patient?->name ?? '#' }}
                                <small>({{ $row->bedLabel() }})</small>
                            </button>
                        @endforeach

                        @if ($queue->isEmpty() && $wardList->isEmpty())
                            <p class="text-muted mb-0" style="font-size:12.5px">No OPD queue or admitted patients today.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
