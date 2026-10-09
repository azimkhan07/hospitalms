<div class="content">
    <div class="container">
        <div class="page-title d-flex align-items-center justify-content-between flex-wrap">
            <h3 class="text-info"><i class="fas fa-notes-medical mr-1"></i> Drug Chart</h3>
            <div class="text-muted" style="font-size:12.5px">
                {{ $stays->count() }} admitted &middot; {{ $recentAlerts->count() }} open alert(s)
            </div>
        </div>

        @if (session()->has('message'))
            <div class="alert alert-success py-1 px-2">{{ session('message') }}</div>
        @endif
        @if (session()->has('error'))
            <div class="alert alert-danger py-1 px-2">{{ session('error') }}</div>
        @endif

        <div class="row">
            <div class="col-lg-3">
                <div class="box box-primary">
                    <div class="box-header"><h3 class="box-title"><i class="fas fa-procedures text-info mr-1"></i> Admitted patients</h3></div>
                    <div class="box-body">
                        <div class="form-group">
                            <select class="form-control form-control-sm" wire:model="stay_id">
                                <option value="">Choose a stay</option>
                                @foreach ($stays as $row)
                                    <option value="{{ $row->id }}">
                                        {{ $row->patient?->name ?? 'Removed patient' }} — {{ $row->bedLabel() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('stay_id') <span class="text-danger" style="font-size:10.5px">{{ $message }}</span> @enderror
                            @if ($stays->isEmpty())
                                <span class="text-muted" style="font-size:10.5px">Nobody is admitted right now.</span>
                            @endif
                        </div>

                        <hr class="my-2">
                        <div class="form-group">
                            <label>Medicine</label>
                            <input type="text" class="form-control form-control-sm" wire:model="medicine"
                                placeholder="Amoxicillin 500mg">
                            @error('medicine') <span class="text-danger" style="font-size:10.5px">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label>Dosage</label>
                                <input type="text" class="form-control form-control-sm" wire:model="dosage" placeholder="1 capsule">
                            </div>
                            <div class="form-group col-6">
                                <label>Frequency</label>
                                <input type="text" class="form-control form-control-sm" wire:model="frequency" placeholder="TDS">
                            </div>
                        </div>
                        @error('dosage') <span class="text-danger" style="font-size:10.5px">{{ $message }}</span> @enderror
                        @error('frequency') <span class="text-danger" style="font-size:10.5px">{{ $message }}</span> @enderror
                        <div class="form-row">
                            <div class="form-group col-6">
                                <label>Route</label>
                                <select class="form-control form-control-sm" wire:model="route">
                                    <option value="">—</option>
                                    <option value="oral">Oral</option>
                                    <option value="iv">IV</option>
                                    <option value="im">IM</option>
                                    <option value="topical">Topical</option>
                                </select>
                            </div>
                            <div class="form-group col-6">
                                <label>Days</label>
                                <input type="number" min="1" max="365" class="form-control form-control-sm" wire:model="duration_days">
                            </div>
                        </div>
                        @error('route') <span class="text-danger" style="font-size:10.5px">{{ $message }}</span> @enderror
                        @error('duration_days') <span class="text-danger" style="font-size:10.5px">{{ $message }}</span> @enderror
                        <div class="form-group">
                            <label>Start date</label>
                            <input type="date" class="form-control form-control-sm" wire:model="start_date">
                            @error('start_date') <span class="text-danger" style="font-size:10.5px">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea rows="2" class="form-control form-control-sm" wire:model="notes"
                                placeholder="With meals, hold if..."></textarea>
                            @error('notes') <span class="text-danger" style="font-size:10.5px">{{ $message }}</span> @enderror
                        </div>
                        <button class="btn btn-sm btn-primary" wire:click="addChart">
                            <i class="fas fa-plus"></i> Add chart line
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="box box-primary">
                    <div class="box-header"><h3 class="box-title"><i class="fas fa-tablets text-info mr-1"></i> Chart &amp; administrations</h3></div>
                    <div class="box-body">
                        @if ($stay_id === '')
                            <p class="text-muted mb-0" style="font-size:12.5px">Pick a stay to see its chart.</p>
                        @elseif ($charts->isEmpty())
                            <p class="text-muted mb-0" style="font-size:12.5px">No medicines charted for this stay yet.</p>
                        @else
                            <div class="form-group">
                                <input type="text" class="form-control form-control-sm" wire:model="note"
                                    placeholder="Bedside note for the next dose (given after food, refused...)">
                            </div>

                            @foreach ($charts as $chart)
                                <div class="mb-2" style="border:1px solid #e3ebf3; border-radius:3px; padding:6px">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                                        <div>
                                            <strong style="font-size:13px">{{ $chart->medicine }}</strong>
                                            <span class="badge badge-{{ $chart->status === 'active' ? 'info' : ($chart->status === 'completed' ? 'success' : 'secondary') }}">
                                                {{ $chart->status }}
                                            </span>
                                            <small class="text-muted">
                                                {{ $chart->dosage ?? '' }} {{ $chart->frequency ?? '' }}
                                                {{ $chart->route ? '/ '.strtoupper($chart->route) : '' }}
                                                {{ $chart->duration_days ? '/ '.$chart->duration_days.'d' : '' }}
                                                &middot; {{ optional($chart->start_date)->format('d M Y') ?? '-' }}
                                                &middot; by {{ $chart->orderer?->name ?? '-' }}
                                            </small>
                                        </div>
                                        <div>
                                            <button class="btn btn-xs btn-outline-success mr-1" wire:click="administer({{ $chart->id }}, 'given')" title="Given">
                                                <i class="fas fa-check"></i> Given
                                            </button>
                                            <button class="btn btn-xs btn-outline-warning mr-1" wire:click="administer({{ $chart->id }}, 'skipped')" title="Skipped">
                                                <i class="fas fa-forward"></i>
                                            </button>
                                            <button class="btn btn-xs btn-outline-danger mr-1" wire:click="administer({{ $chart->id }}, 'refused')" title="Refused">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                            @if ($chart->status === 'active')
                                                <button class="btn btn-xs btn-outline-secondary mr-1" wire:click="completeChart({{ $chart->id }})" title="Complete">
                                                    <i class="fas fa-flag-checkered"></i>
                                                </button>
                                                <button class="btn btn-xs btn-outline-secondary" wire:click="cancelChart({{ $chart->id }})" title="Cancel">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </div>

                                    @if ($chart->notes)
                                        <small class="text-muted d-block">{{ $chart->notes }}</small>
                                    @endif

                                    @forelse ($chart->administrations as $admin)
                                        <div class="d-flex justify-content-between align-items-center"
                                            style="font-size:12px; border-top:1px dashed #e3ebf3; padding-top:3px">
                                            <span>
                                                <span class="badge badge-{{ ['given' => 'success', 'skipped' => 'warning', 'refused' => 'danger', 'due' => 'secondary'][$admin->state] ?? 'secondary' }}">
                                                    {{ $admin->state }}
                                                </span>
                                                {{ $admin->note ? ' '.$admin->note : '' }}
                                            </span>
                                            <small class="text-muted">
                                                {{ optional($admin->given_at)->format('d M, h:i A') ?? '-' }}
                                                &middot; {{ $admin->giver?->name ?? '-' }}
                                            </small>
                                        </div>
                                    @empty
                                        <small class="text-muted" style="font-size:11.5px">No doses recorded yet.</small>
                                    @endforelse
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-3">
                <div class="box box-danger">
                    <div class="box-header"><h3 class="box-title"><i class="fas fa-exclamation-triangle text-danger mr-1"></i> Doctor alert</h3></div>
                    <div class="box-body">
                        <div class="form-group">
                            <label>Category</label>
                            <select class="form-control form-control-sm" wire:model="alert_category">
                                @foreach ($categories as $category)
                                    <option value="{{ $category }}">{{ ucfirst($category) }}</option>
                                @endforeach
                            </select>
                            @error('alert_category') <span class="text-danger" style="font-size:10.5px">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label>Message</label>
                            <textarea rows="3" class="form-control form-control-sm" wire:model="alert_message"
                                placeholder="What should the doctor know?"></textarea>
                            @error('alert_message') <span class="text-danger" style="font-size:10.5px">{{ $message }}</span> @enderror
                        </div>
                        <label class="d-block mb-2" style="font-size:12.5px">
                            <input type="checkbox" wire:model="alert_urgent"> Urgent
                        </label>
                        <button class="btn btn-sm btn-danger" wire:click="raiseAlert">
                            <i class="fas fa-bell"></i> Alert doctor
                        </button>

                        <hr class="my-2">
                        <div style="font-size:12.5px">
                            <strong>Open alerts</strong>
                            @forelse ($recentAlerts as $alert)
                                <div class="mt-1">
                                    @if ($alert->is_urgent)
                                        <span class="badge badge-danger">URGENT</span>
                                    @endif
                                    <span class="badge badge-secondary">{{ $alert->category }}</span>
                                    <span>{{ \Illuminate\Support\Str::limit($alert->message, 60) }}</span>
                                    <small class="text-muted d-block">{{ $alert->patient?->name ?? 'Ward' }}
                                        &middot; {{ $alert->created_at->diffForHumans() }}</small>
                                </div>
                            @empty
                                <span class="text-muted">No open alerts.</span>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
