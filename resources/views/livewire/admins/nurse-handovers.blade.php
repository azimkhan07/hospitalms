<div class="content">
    <div class="container">
        <div class="page-title d-flex align-items-center justify-content-between flex-wrap">
            <h3 class="text-info"><i class="fas fa-exchange-alt mr-1"></i> Shift Handover</h3>
            <div class="text-muted" style="font-size:12.5px">Last {{ $handovers->count() }} handover(s)</div>
        </div>

        @if (session()->has('message'))
            <div class="alert alert-success py-1 px-2">{{ session('message') }}</div>
        @endif
        @if (session()->has('error'))
            <div class="alert alert-danger py-1 px-2">{{ session('error') }}</div>
        @endif

        <div class="row">
            <div class="col-lg-4">
                <div class="box box-primary">
                    <div class="box-header"><h3 class="box-title"><i class="fas fa-pen text-info mr-1"></i> New handover</h3></div>
                    <div class="box-body">
                        <div class="form-group">
                            <label>Ward</label>
                            <input type="text" class="form-control form-control-sm" wire:model="ward"
                                placeholder="Male Ward / ICU / General">
                            @error('ward') <span class="text-danger" style="font-size:10.5px">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label>To role</label>
                            <select class="form-control form-control-sm" wire:model="to_role">
                                <option value="">Anyone on shift</option>
                                <option value="nurse">Nurse</option>
                                <option value="doctor">Doctor</option>
                                <option value="receptionist">Receptionist</option>
                                <option value="admin">Admin</option>
                            </select>
                            @error('to_role') <span class="text-danger" style="font-size:10.5px">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea rows="6" class="form-control form-control-sm" wire:model="notes"
                                placeholder="Bed G1 spiked fever at 2am, culture sent. ICU patient on fluids..."></textarea>
                            @error('notes') <span class="text-danger" style="font-size:10.5px">{{ $message }}</span> @enderror
                        </div>
                        <button class="btn btn-sm btn-primary" wire:click="createHandover">
                            <i class="fas fa-save"></i> Record handover
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="box box-primary">
                    <div class="box-header"><h3 class="box-title"><i class="fas fa-history text-info mr-1"></i> Recent handovers</h3></div>
                    <div class="box-body">
                        <table class="table table-sm table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th style="width:130px">When</th>
                                    <th style="width:140px">From</th>
                                    <th style="width:130px">Ward / To</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($handovers as $row)
                                    <tr>
                                        <td>{{ $row->created_at->format('d M, h:i A') }}</td>
                                        <td>{{ $row->originator?->name ?? '-' }}</td>
                                        <td>
                                            {{ $row->ward ?? '-' }}
                                            @if ($row->to_role)
                                                <span class="badge badge-info d-block">{{ ucfirst($row->to_role) }}</span>
                                            @endif
                                        </td>
                                        <td style="font-size:12.5px">{{ $row->notes }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-3">No handovers recorded yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
