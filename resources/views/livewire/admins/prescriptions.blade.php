<div class="content">
    <div class="container">
        <div class="page-title">
            <h3 class="text-info">Prescriptions</h3>
        </div>

        <div class="box box-primary">
            <div class="box-header d-flex align-items-center justify-content-between flex-wrap">
                <h3 class="box-title"><i class="fas fa-file-prescription text-info mr-1"></i> Prescription Register</h3>
                <div class="d-flex align-items-center">
                    <input type="search" class="form-control form-control-sm mr-1" style="max-width:200px"
                        placeholder="Search patient, doctor, medicine..." wire:model.live.debounce.300ms="search">
                    <button type="button" class="btn btn-sm btn-primary" wire:click="$toggle('showForm')">
                        <i class="fas fa-plus"></i> New Prescription
                    </button>
                </div>
            </div>

            <div class="box-body">
                @if (session()->has('success'))
                    <div class="alert alert-success py-1 px-2">{{ session('success') }}</div>
                @endif

                @if ($showForm)
                    <form wire:submit.prevent="save" class="border rounded p-2 mb-2">
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>Patient</label>
                                <select class="form-control form-control-sm" wire:model="patientId">
                                    <option value="">Choose patient</option>
                                    @foreach ($patients as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                                    @endforeach
                                </select>
                                @error('patientId')
                                    <span class="text-danger" style="font-size:10.5px">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="form-group col-md-4">
                                <label>Doctor</label>
                                <input type="text" class="form-control form-control-sm"
                                    value="{{ auth()->user()->name }}" readonly>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Notes</label>
                                <input type="text" class="form-control form-control-sm"
                                    placeholder="Advice, follow-up..." wire:model="notes">
                            </div>
                        </div>

                        <table class="table table-sm mb-1" style="background:transparent">
                            <thead>
                                <tr>
                                    <th style="width:32%">Medicine</th>
                                    <th style="width:18%">Dosage</th>
                                    <th style="width:18%">Frequency</th>
                                    <th style="width:16%">Duration</th>
                                    <th style="width:14%">Note</th>
                                    <th style="width:28px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($items as $i => $item)
                                    <tr>
                                        <td>
                                            <input list="hms-medicines" type="text"
                                                class="form-control form-control-sm" wire:model="items.{{ $i }}.medicine">
                                            <datalist id="hms-medicines">
                                                @foreach ($medicines as $m)
                                                    <option value="{{ $m->name }}"></option>
                                                @endforeach
                                            </datalist>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm"
                                                placeholder="1 tab" wire:model="items.{{ $i }}.dosage">
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm"
                                                placeholder="After meal" wire:model="items.{{ $i }}.frequency">
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm"
                                                placeholder="5 days" wire:model="items.{{ $i }}.duration">
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm"
                                                wire:model="items.{{ $i }}.note">
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-xs btn-outline-danger"
                                                wire:click="removeRow({{ $i }})" title="Remove">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div class="d-flex">
                            <button type="button" class="btn btn-xs btn-outline-secondary mr-1" wire:click="addRow">
                                <i class="fas fa-plus"></i> Add row
                            </button>
                            <button type="submit" class="btn btn-sm btn-success ml-auto">
                                <i class="fas fa-check"></i> Issue Prescription
                            </button>
                        </div>
                    </form>
                @endif

                <div class="text-info" wire:loading>Loading..</div>

                @forelse ($prescriptions as $rx)
                    <div class="border rounded p-2 mb-1">
                        <div class="d-flex justify-content-between align-items-start flex-wrap">
                            <div>
                                <strong>{{ $rx->patient?->name ?? 'Removed patient' }}</strong>
                                <span class="text-muted" style="font-size:11px">
                                    &middot; {{ $rx->doctor?->name ?? 'Unknown doctor' }}
                                    &middot; {{ $rx->issued_at?->format('d M Y, h:i A') ?? 'Not issued' }}
                                </span>
                                @if ($rx->notes)
                                    <div class="text-muted" style="font-size:11px">{{ $rx->notes }}</div>
                                @endif
                            </div>
                            <div class="text-right">
                                <span class="badge badge-sm {{ $rx->status === 'issued' ? 'badge-success' : ($rx->status === 'draft' ? 'badge-warning' : 'badge-secondary') }}">
                                    {{ ucfirst($rx->status) }}
                                </span>
                                @if ($rx->status === 'issued')
                                    <button type="button" class="btn btn-xs btn-outline-danger ml-1"
                                        wire:click="cancel({{ $rx->id }})">Cancel</button>
                                @endif
                            </div>
                        </div>
                        <table class="table table-sm mb-0 mt-1" style="background:transparent">
                            <thead>
                                <tr>
                                    <th>Medicine</th>
                                    <th>Dosage</th>
                                    <th>Frequency</th>
                                    <th>Duration</th>
                                    <th>Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rx->items as $item)
                                    <tr>
                                        <td>{{ $item->medicine }}</td>
                                        <td>{{ $item->dosage ?? '-' }}</td>
                                        <td>{{ $item->frequency ?? '-' }}</td>
                                        <td>{{ $item->duration ?? '-' }}</td>
                                        <td>{{ $item->note ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @empty
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-file-prescription fa-2x mb-1 d-block"></i>
                        No prescriptions recorded yet.
                    </div>
                @endforelse

                @if ($prescriptions->hasPages())
                    <div class="mt-2">{{ $prescriptions->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>