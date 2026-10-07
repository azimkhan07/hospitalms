<div class="content">
    <div class="container">
        <div class="page-title">
            <h3 class="text-info">Appointments</h3>
        </div>

        @if (session()->has('message'))
            <div class="alert alert-success py-1 px-2">
                {{ session('message') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        <div class="row">
            <div class="col-lg-4">
                <div class="box box-primary">
                    <div class="box-header">
                        <h3 class="box-title">
                            <i class="fas fa-calendar-plus text-info mr-1"></i>
                            {{ $edit_appointment_id ? 'Edit Appointment' : 'Add New Appointment' }}
                        </h3>
                    </div>
                    <div class="box-body">
                        <form wire:submit.prevent="add_appointment">
                            <div class="form-group">
                                <label>Patient</label>
                                <select class="form-control form-control-sm" wire:model="patient">
                                    <option value="">Choose patient</option>
                                    @foreach ($patients as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                                    @endforeach
                                </select>
                                @error('patient')
                                    <span class="text-danger" style="font-size:10.5px">{{ $message }}</span>
                                @enderror
                                @if ($patients->isEmpty())
                                    <span class="text-muted" style="font-size:10.5px">No patients registered yet.</span>
                                @endif
                            </div>

                            <div class="form-group">
                                <label>Doctor</label>
                                <select class="form-control form-control-sm" wire:model="doctor">
                                    <option value="">Choose doctor</option>
                                    @foreach ($doctors as $d)
                                        <option value="{{ $d->id }}">{{ $d->employ?->name ?? 'Doctor #'.$d->id }}</option>
                                    @endforeach
                                </select>
                                @error('doctor')
                                    <span class="text-danger" style="font-size:10.5px">{{ $message }}</span>
                                @enderror
                                @if ($doctors->isEmpty())
                                    <span class="text-muted" style="font-size:10.5px">No doctors registered yet.</span>
                                @endif
                            </div>

                            <div class="form-row">
                                <div class="form-group col-6">
                                    <label>Angio machine <span class="text-muted">(optional)</span></label>
                                    <select class="form-control form-control-sm" wire:model="angioMachineId">
                                        <option value="">No angio machine</option>
                                        @foreach ($angioMachines ?? [] as $am)
                                            <option value="{{ $am->id }}">{{ $am->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('angioMachineId')
                                        <span class="text-danger" style="font-size:10.5px">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="form-group col-6">
                                    <label>Govt scheme <span class="text-muted">(optional)</span></label>
                                    <select class="form-control form-control-sm" wire:model="schemeId">
                                        <option value="">No scheme</option>
                                        @foreach ($schemes ?? [] as $s)
                                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('schemeId')
                                        <span class="text-danger" style="font-size:10.5px">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-6">
                                    <label>Start</label>
                                    <input type="datetime-local" class="form-control form-control-sm"
                                        wire:model="start_timeee">
                                    @error('start_timeee')
                                        <span class="text-danger" style="font-size:10.5px">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="form-group col-6">
                                    <label>End</label>
                                    <input type="datetime-local" class="form-control form-control-sm"
                                        wire:model="endtime">
                                    @error('endtime')
                                        <span class="text-danger" style="font-size:10.5px">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Status</label>
                                <select class="form-control form-control-sm" wire:model="status">
                                    @foreach (['pending', 'confirmed', 'completed', 'cancelled'] as $s)
                                        <option value="{{ $s }}">{{ ucfirst($s) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Notes <span class="text-muted">(optional)</span></label>
                                <textarea rows="2" class="form-control form-control-sm" wire:model="notes"
                                    placeholder="Reason, referral, preparation..."></textarea>
                            </div>

                            <div class="d-flex">
                                @if ($edit_appointment_id)
                                    <button type="button" class="btn btn-sm btn-outline-secondary mr-1"
                                        wire:click="cancelEdit">Cancel</button>
                                @endif
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="fas fa-check"></i> {{ $button_text }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="box box-primary">
                    <div class="box-header d-flex align-items-center justify-content-between flex-wrap">
                        <h3 class="box-title"><i class="fas fa-list text-info mr-1"></i> All Appointments</h3>
                        <input type="search" class="form-control form-control-sm" style="max-width:220px"
                            placeholder="Search patient..." wire:model.live.debounce.300ms="search">
                    </div>
                    <div class="box-body">
                        <div class="text-info" wire:loading>Loading..</div>

                        <table class="table table-sm table-bordered mb-0">
                            <thead>
<tr>
                                <th>#</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Treatment</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Status</th>
                                <th style="width:90px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($appointments as $item)
                                <tr>
                                    <td>{{ $item->id }}</td>
                                    <td>{{ $item->patient?->name ?? 'Removed patient' }}</td>
                                    <td>{{ $item->doctor?->employ?->name ?? '-' }}</td>
                                    <td>
                                        @if ($item->angioMachine)
                                            <span class="label label-danger" title="Done on angio machine">{{ $item->angioMachine->name }}</span>
                                        @endif
                                        @if ($item->scheme)
                                            <span class="label label-info" title="Under Govt scheme">{{ $item->scheme->name }}</span>
                                        @endif
                                        @if (! $item->angioMachine && ! $item->scheme)
                                            <span class="text-muted" style="font-size:11px">-</span>
                                        @endif
                                    </td>
                                    <td>{{ optional($item->intime)->format('d M Y, h:i A') ?? '-' }}</td>
                                        <td>{{ optional($item->outtime)->format('d M Y, h:i A') ?? 'Ongoing' }}</td>
                                        <td>
                                            <span class="badge badge-sm
                                                @if ($item->status === 'completed') badge-success
                                                @elseif ($item->status === 'cancelled') badge-danger
                                                @elseif ($item->status === 'confirmed') badge-info
                                                @else badge-warning @endif">
                                                {{ ucfirst($item->status ?? 'pending') }}
                                            </span>
                                        </td>
                                        <td class="text-right">
                                            <button wire:click="edit({{ $item->id }})" class="btn btn-xs btn-outline-info"
                                                title="Edit"><i class="fas fa-pen"></i></button>
                                            <button wire:click="delete({{ $item->id }})"
                                                onclick="return confirm('Delete this appointment?')"
                                                class="btn btn-xs btn-outline-danger" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-3">No appointments recorded.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>

                        @if ($appointments->hasPages())
                            <div class="mt-2">{{ $appointments->links() }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>