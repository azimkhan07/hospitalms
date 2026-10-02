<div class="content">
    <div class="container">
        <div class="page-title">
            <h3 class="text-info">Patient History</h3>
        </div>

        <div class="row">
            <div class="col-lg-3">
                <div class="box box-primary">
                    <div class="box-header">
                        <h3 class="box-title"><i class="fas fa-users text-info mr-1"></i> Patients</h3>
                    </div>
            <div class="box-body p-0">
                <div class="p-2 border-bottom">
                    <input type="search" class="form-control form-control-sm"
                        placeholder="Search name, phone, blood group..." wire:model.live.debounce.300ms="search">
                </div>
                <div style="max-height:460px;overflow-y:auto">
                    @forelse ($patients as $p)
                        <button type="button"
                            class="btn btn-block btn-light text-left {{ $patientId === $p->id ? 'active' : '' }}"
                            style="border-radius:0;border-bottom:1px solid #eef2f7;font-size:11.5px"
                            wire:click="select({{ $p->id }})">
                            {{ $p->name }}
                            <span class="text-muted" style="font-size:10.5px">
                                &middot; {{ $p->bloodgroup ?? 'n/a' }}
                            </span>
                        </button>
                    @empty
                        <div class="p-3 text-muted" style="font-size:11.5px">No patients found.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-9">
        @if (! $selected)
            <div class="box box-primary">
                <div class="box-body text-center text-muted py-5">
                    <i class="fas fa-history fa-3x mb-2 d-block"></i>
                    Select a patient from the list to view their complete history.
                </div>
            </div>
        @else
            <div class="box box-primary">
                <div class="box-header d-flex justify-content-between align-items-center flex-wrap">
                    <h3 class="box-title">
                        <i class="fas fa-history text-info mr-1"></i>
                        {{ $selected->name }}
                        <small class="text-muted">
                            {{ $selected->age ? $selected->age.' yrs' : '' }}
                            {{ $selected->gender ? ' / '.ucfirst($selected->gender) : '' }}
                            {{ $selected->bloodgroup ? ' / '.$selected->bloodgroup : '' }}
                        </small>
                    </h3>
                    <div>
                        <span class="badge badge-info">{{ $selected->appointments_count ?? 0 }} appointments</span>
                        <span class="badge badge-success">{{ $selected->prescriptions_count ?? 0 }} prescriptions</span>
                        <span class="badge badge-secondary">{{ $selected->stays_count ?? 0 }} admissions</span>
                    </div>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="font-weight-bold border-bottom pb-1 mb-1" style="font-size:11.5px">
                                Contact
                            </div>
                            <div style="font-size:11.5px">
                                <div><i class="fas fa-phone mr-1"></i>{{ $selected->phone ?? '-' }}</div>
                                <div><i class="fas fa-envelope mr-1"></i>{{ $selected->email ?? '-' }}</div>
                                <div><i class="fas fa-map-marker-alt mr-1"></i>{{ $selected->address ?? '-' }}</div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="font-weight-bold border-bottom pb-1 mb-1" style="font-size:11.5px">
                                Appointments
                            </div>
                            <table class="table table-sm mb-0" style="background:transparent">
                                <thead>
                                    <tr>
                                        <th>In</th>
                                        <th>Out</th>
                                        <th>Doctor</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($appointments as $a)
                                        <tr>
                                            <td>{{ optional($a->intime)->format('d M Y') ?? '-' }}</td>
                                            <td>{{ optional($a->outtime)->format('d M Y') ?? 'Ongoing' }}</td>
                                            <td>{{ $a->doctor?->employ?->name ?? '-' }}</td>
                                            <td>
                                                <span class="badge badge-sm {{ $a->status === 'completed' ? 'badge-success' : ($a->status === 'cancelled' ? 'badge-danger' : 'badge-info') }}">
                                                    {{ ucfirst($a->status ?? 'pending') }}
                                                </span>
                                            </td>
                                            <td>{{ \Illuminate\Support\Str::limit($a->notes ?? $a->description ?? '-', 40) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-2">No appointments.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="row mt-2">
                        <div class="col-md-6">
                            <div class="font-weight-bold border-bottom pb-1 mb-1" style="font-size:11.5px">
                                Admissions
                            </div>
                            <table class="table table-sm mb-0" style="background:transparent">
                                <thead>
                                    <tr>
                                        <th>Start</th>
                                        <th>End</th>
                                        <th>Room</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($stays as $s)
                                        <tr>
                                            <td>{{ optional($s->start_time)->format('d M Y') ?? '-' }}</td>
                                            <td>{{ optional($s->end_time)->format('d M Y') ?? 'Ongoing' }}</td>
                                            <td>
                                                {{ $s->room?->department?->name ?? 'Room #'.($s->room_id ?? '-') }}
                                            </td>
                                            <td>{{ $s->total ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-2">No admissions.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <div class="font-weight-bold border-bottom pb-1 mb-1" style="font-size:11.5px">
                                Prescriptions
                            </div>
                            <table class="table table-sm mb-0" style="background:transparent">
                                <thead>
                                    <tr>
                                        <th>Issued</th>
                                        <th>Doctor</th>
                                        <th>Items</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($prescriptions as $rx)
                                        <tr>
                                            <td>{{ $rx->issued_at?->format('d M Y') ?? '-' }}</td>
                                            <td>{{ $rx->doctor?->name ?? '-' }}</td>
                                            <td>{{ \Illuminate\Support\Str::limit($rx->items->pluck('medicine')->join(', ') ?: '-', 40) }}</td>
                                            <td>
                                                <span class="badge badge-sm {{ $rx->status === 'issued' ? 'badge-success' : 'badge-secondary' }}">
                                                    {{ ucfirst($rx->status) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-2">No prescriptions.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
        </div>
    </div>
</div>