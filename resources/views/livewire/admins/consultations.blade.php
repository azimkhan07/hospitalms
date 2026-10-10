<div class="content">
    <div class="container">
        <div class="page-title d-flex align-items-center justify-content-between flex-wrap">
            <h3 class="text-info"><i class="fas fa-stethoscope mr-1"></i> Consultations (OPD)</h3>
            <div class="d-flex align-items-center flex-wrap">
                <span class="text-muted" style="font-size:12.5px">
                    Today {{ $todayCount ?? 0 }} &middot; Waiting {{ $waitingCount ?? 0 }} &middot; Treated {{ $treatedToday ?? 0 }}
                </span>
                @if ($doctorProfile)
                    <span class="badge badge-{{ $doctorProfile->on_duty ? 'success' : 'secondary' }} ml-2"
                        title="{{ $doctorProfile->on_duty ? 'You are accepting patients' : 'You are off duty' }}">
                        {{ $doctorProfile->on_duty ? 'On duty' : 'Off duty' }}
                    </span>
                    <button class="btn btn-sm btn-outline-secondary ml-1" wire:click="toggleDuty" title="Flip your availability">
                        <i class="fas fa-user-md"></i> Toggle
                    </button>
                    <button class="btn btn-sm btn-success ml-1" wire:click="callNext"
                        {{ ($waitingCount ?? 0) === 0 ? 'disabled' : '' }}>
                        <i class="fas fa-bell"></i> Call next
                    </button>
                @endif
            </div>
        </div>

        @if ($this->recentAlerts->isNotEmpty())
            <div class="box box-danger mb-2">
                <div class="box-header py-2">
                    <h3 class="box-title" style="font-size:13px">
                        <i class="fas fa-exclamation-triangle text-danger mr-1"></i> Doctor Alerts
                        <span class="badge badge-danger">{{ $this->recentAlerts->count() }}</span>
                    </h3>
                </div>
                <div class="box-body py-1">
                    @foreach ($this->recentAlerts as $alert)
                        <div class="d-flex justify-content-between align-items-center mb-1" style="font-size:12.5px">
                            <span>
                                @if ($alert->is_urgent)
                                    <span class="badge badge-danger mr-1">URGENT</span>
                                @endif
                                <span class="badge badge-secondary mr-1">{{ $alert->category }}</span>
                                {{ $alert->message }}
                                <small class="text-muted">&mdash; {{ $alert->patient?->name ?? 'Ward' }}</small>
                            </span>
                            <button class="btn btn-xs btn-outline-success ml-2"
                                wire:click="acknowledgeAlert({{ $alert->id }})">
                                <i class="fas fa-check"></i> Acknowledge
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if (($waitingQueue ?? collect())->isNotEmpty() && ! $open)
            <div class="box box-warning mb-2" wire:poll.10s>
                <div class="box-header d-flex align-items-center justify-content-between py-2">
                    <h3 class="box-title" style="font-size:13px">
                        <i class="fas fa-user-clock text-warning mr-1"></i> My queue today
                    </h3>
                    <span class="text-muted" style="font-size:11.5px">{{ $waitingQueue->count() }} waiting</span>
                </div>
                <div class="box-body py-1">
                    <div class="d-flex flex-wrap">
                        @foreach ($waitingQueue as $row)
                            <span class="mr-2 mb-1 px-2 py-1 rounded"
                                style="font-size:12px; background:#fff7e6;
                                {{ $row->status === 'called' ? 'outline:2px solid #dd4b39;' : '' }}">
                                <b>#{{ $row->token ?? '-' }}</b>
                                {{ $row->patient?->name ?? '-' }}
                                @if ($row->status === 'called')
                                    <i class="fas fa-bell text-danger" title="Called — waiting to enter"></i>
                                @endif
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        @if (session()->has('message'))
            <div class="alert alert-success py-1 px-2">{{ session('message') }}</div>
        @endif
        @if (session()->has('error'))
            <div class="alert alert-danger py-1 px-2">{{ session('error') }}</div>
        @endif

        @if (! $doctorProfile)
            <div class="alert alert-warning">
                Your login is not linked to a doctor profile yet, so there are no appointments assigned to you.
                Ask the admin to link your account from Staff &rarr; Doctor.
            </div>
        @endif

        @if ($open)
            <div class="row">
                <div class="col-lg-7">
                    <div class="box box-primary">
                        <div class="box-header d-flex align-items-center justify-content-between">
                            <h3 class="box-title">
                                <i class="fas fa-notes-medical text-info mr-1"></i>
                                {{ $open->patient?->name ?? 'Patient' }}
                                @if ($open->patient)
                                    <small class="text-muted">
                                        {{ $open->patient->age ? $open->patient->age.'y' : '' }}
                                        {{ $open->patient->gender ? '/ '.$open->patient->gender : '' }}
                                        {{ $open->patient->bloodgroup ? '/ '.$open->patient->bloodgroup : '' }}
                                    </small>
                                @endif
                            </h3>
                            <button class="btn btn-sm btn-outline-secondary" wire:click="close">
                                <i class="fas fa-times"></i> Close
                            </button>
                        </div>
                        <div class="box-body">
                            <form wire:submit.prevent="saveConsult">
                                <div class="form-group">
                                    <label>Chief complaint</label>
                                    <textarea rows="2" class="form-control form-control-sm" wire:model="chiefComplaint"
                                        placeholder="What the patient reports..."></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Diagnosis / findings</label>
                                    <textarea rows="3" class="form-control form-control-sm" wire:model="diagnosis"
                                        placeholder="Assessment after examination..."></textarea>
                                </div>
                                <div class="form-group">
                                    <label class="d-flex align-items-center">
                                        ICD-10 code
                                        @if ($open->icd10)
                                            <span class="badge badge-info ml-2">
                                                {{ $open->icd10->code }} &mdash; {{ $open->icd10->description }}
                                            </span>
                                        @endif
                                    </label>
                                    <input type="text" class="form-control form-control-sm" list="icd10-options"
                                        wire:model="icd" maxlength="10" placeholder="Type or pick a code, e.g. J06.9">
                                    <datalist id="icd10-options">
                                        @foreach ($icdCodes as $row)
                                            <option value="{{ $row['code'] }}">{{ $row['code'] }} &mdash; {{ $row['description'] }}</option>
                                        @endforeach
                                    </datalist>
                                    @error('icd') <span class="text-danger" style="font-size:10.5px">{{ $message }}</span> @enderror
                                    <small class="text-muted d-block" style="font-size:11px">Pick a suggestion to store the exact code.</small>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-4">
                                        <label>Follow-up date</label>
                                        <input type="date" class="form-control form-control-sm" wire:model="followUpAt">
                                    </div>
                                    <div class="form-group col-8 d-flex align-items-end">
                                        <button type="submit" class="btn btn-sm btn-primary mr-1">
                                            <i class="fas fa-save"></i> Save consult
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-info mr-1"
                                            wire:click="prescribe({{ $open->id }})">
                                            <i class="fas fa-file-prescription"></i> Prescribe
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                            wire:click="goVitals({{ $open->id }})">
                                            <i class="fas fa-heartbeat"></i> Vitals
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <div class="d-flex flex-wrap">
                                @if (in_array($open->status, ['pending', 'confirmed', 'waiting', 'called'], true))
                                    <button class="btn btn-sm btn-outline-success mr-1 mb-1" wire:click="start({{ $open->id }})">
                                        <i class="fas fa-play"></i> Start consult
                                    </button>
                                @endif
                                @if ($open->status !== 'completed')
                                    <button class="btn btn-sm btn-success mr-1 mb-1" wire:click="complete({{ $open->id }})">
                                        <i class="fas fa-check"></i> Mark treated
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="box box-primary">
                        <div class="box-header"><h3 class="box-title"><i class="fas fa-heartbeat text-danger mr-1"></i> Latest vitals</h3></div>
                        <div class="box-body">
                            @if ($latestVital)
                                <table class="table table-sm table-bordered mb-0" style="font-size:12.5px">
                                    <tr>
                                        <th>BP</th><td>{{ $latestVital->bpLabel() }}</td>
                                        <th>Pulse</th><td>{{ $latestVital->pulse ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Temp</th><td>{{ $latestVital->temperature ?? '-' }} &deg;C</td>
                                        <th>SpO2</th><td>{{ $latestVital->spo2 ?? '-' }} %</td>
                                    </tr>
                                    <tr>
                                        <th>Weight</th><td>{{ $latestVital->weight ?? '-' }} kg</td>
                                        <th>Taken</th><td>{{ optional($latestVital->taken_at)->format('d M, h:i A') }}</td>
                                    </tr>
                                </table>
                            @else
                                <p class="text-muted mb-0" style="font-size:12.5px">No vitals recorded for this patient yet.</p>
                            @endif
                        </div>
                    </div>

                    <div class="box box-primary">
                        <div class="box-header"><h3 class="box-title"><i class="fas fa-vials text-info mr-1"></i> Lab orders</h3></div>
                        <div class="box-body">
                            @forelse ($appointmentReports as $report)
                                <div class="d-flex justify-content-between align-items-center mb-1" style="font-size:12.5px">
                                    <span>
                                        {{ $report->test?->name ?? 'Test #'.$report->investigation_test_id }}
                                        <span class="badge badge-{{ $report->status === 'reported' ? 'success' : ($report->status === 'cancelled' ? 'secondary' : 'warning') }}">
                                            {{ str_replace('_', ' ', $report->status) }}
                                        </span>
                                    </span>
                                    <span class="text-muted">{{ $report->priority === 'stat' ? 'STAT' : '' }}</span>
                                </div>
                            @empty
                                <p class="text-muted mb-1" style="font-size:12.5px">Nothing ordered on this visit.</p>
                            @endforelse

                            <hr class="my-2">
                            <div class="form-group mb-1">
                                <label style="font-size:12.5px">Order tests</label>
                                <div style="max-height:150px; overflow:auto; border:1px solid #e3ebf3; border-radius:3px; padding:4px">
                                    @foreach ($availableTests as $test)
                                        <label class="d-block mb-0" style="font-size:12.5px">
                                            <input type="checkbox" wire:model="orderTests" value="{{ $test->id }}">
                                            {{ $test->name }} @if($test->code)<small class="text-muted">({{ $test->code }})</small>@endif
                                        </label>
                                    @endforeach
                                </div>
                                @error('orderTests') <span class="text-danger" style="font-size:10.5px">{{ $message }}</span> @enderror
                            </div>
                            <div class="form-group mb-1">
                                <select class="form-control form-control-sm" wire:model="orderPriority">
                                    <option value="routine">Routine</option>
                                    <option value="stat">STAT (urgent)</option>
                                </select>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" wire:click="orderInvestigations">
                                <i class="fas fa-paper-plane"></i> Send to lab
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="box box-primary">
                <div class="box-header d-flex align-items-center justify-content-between flex-wrap">
                    <div>
                        @foreach (['today' => 'Today', 'treated' => 'Treated', 'all' => 'All'] as $key => $label)
                            <button class="btn btn-sm {{ $tab === $key ? 'btn-info' : 'btn-outline-info' }} mr-1"
                                wire:click="$set('tab', '{{ $key }}')">{{ $label }}</button>
                        @endforeach
                    </div>
                    <input type="search" class="form-control form-control-sm" style="max-width:220px"
                        placeholder="Search patient..." wire:model.live.debounce.300ms="search">
                </div>
                <div class="box-body">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Patient</th>
                                <th>Complaint</th>
                                <th>Status</th>
                                <th style="width:150px" class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($appointments as $appt)
                                <tr>
                                    <td>{{ optional($appt->intime)->format('d M, h:i A') }}</td>
                                    <td>
                                        {{ $appt->patient?->name ?? 'Removed patient' }}
                                        <small class="text-muted d-block">
                                            {{ $appt->patient?->age ? $appt->patient->age.'y' : '' }}
                                            {{ $appt->patient?->bloodgroup }}
                                        </small>
                                    </td>
                                    <td style="max-width:220px">
                                        <small>{{ \Illuminate\Support\Str::limit((string) $appt->chief_complaint, 70) ?: '-' }}</small>
                                    </td>
                                    <td>
                                        @php
                                            $badge = ['waiting' => 'warning', 'called' => 'danger', 'in_consult' => 'primary', 'completed' => 'success'][$appt->status] ?? 'secondary';
                                        @endphp
                                        <span class="badge badge-{{ $badge }}">{{ str_replace('_', ' ', ucfirst($appt->status)) }}</span>
                                    </td>
                                    <td class="text-right">
                                        <button class="btn btn-xs btn-outline-primary" wire:click="open({{ $appt->id }})" title="Open chart">
                                            <i class="fas fa-folder-open"></i>
                                        </button>
                                        @if (in_array($appt->status, ['pending', 'confirmed', 'waiting', 'called'], true))
                                            <button class="btn btn-xs btn-outline-success" wire:click="start({{ $appt->id }})" title="Start consult">
                                                <i class="fas fa-play"></i>
                                            </button>
                                        @endif
                                        @if ($appt->status !== 'completed' && $appt->status !== 'cancelled')
                                            <button class="btn btn-xs btn-outline-success" wire:click="complete({{ $appt->id }})" title="Mark treated">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        @endif
                                        <button class="btn btn-xs btn-outline-secondary" wire:click="goVitals({{ $appt->id }})" title="Vitals">
                                            <i class="fas fa-heartbeat"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-3">No appointments in this list.</td></tr>
                            @endforelse
                        </tbody>
                    </table>

                    @if ($appointments->hasPages())
                        <div class="mt-2">{{ $appointments->links() }}</div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
