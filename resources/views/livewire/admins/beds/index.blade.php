<div>
    <div class="content">
        <div class="container">
            <div class="row page-title row">
                <div class="col">
                    <h3 class="text-info">{{ env('APP_NAME') }} Beds</h3>
                </div>
                <div class="col-auto">
                    @if ($this->canManage())
                        <button class="btn btn-primary" wire:click="show_create_form">Add New</button>
                    @else
                        <span class="badge badge-secondary">Read-only &mdash; the Dean allocates beds</span>
                    @endif
                </div>
            </div>

            <div>
                @if (session()->has('message'))
                    <div class="alert alert-success">
                        {{ session('message') }}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif
                @if (session()->has('error'))
                    <div class="alert alert-danger">
                        {{ session('error') }}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif
            </div>

            <div class="text-info" wire:loading>Loading..</div>

            @forelse ($sections as $type => $section)
                <div class="box box-primary">
                    <div class="box-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="text-capitalize bg-dark p-2 shadow text-light rounded">
                                {{ $section['label'] }}
                            </div>
                            <div class="text-muted">
                                <span class="badge badge-success">{{ $section['free'] }} free</span>
                                <span class="badge badge-danger">{{ $section['occupied'] }} occupied</span>
                                <span class="badge badge-info">{{ $section['total'] }} total</span>
                            </div>
                        </div>

                        @forelse ($section['rooms'] as $room)
                            <div class="mb-3">
                                <div class="text-muted mb-1">
                                    <strong>{{ $room->name }}</strong>
                                    @if ($room->floor)
                                        &middot; Floor {{ $room->floor }}
                                    @endif
                                    @if ($room->daily_rate)
                                        &middot; {{ number_format((float) $room->daily_rate, 0) }}/day
                                    @endif
                                </div>
                                <div class="row">
                                    @foreach ($room->beds as $bed)
                                        <div class="col-6 col-md-3 col-lg-2 mb-2">
                                            @php
                                                $state = $bed->status === 'alloted' ? 'danger' : ($bed->status === 'available' ? 'success' : 'warning');
                                            @endphp
                                            <div class="card border-{{ $state }} h-100">
                                                <div class="card-body p-2 text-center">
                                                    <div class="h6 mb-1">Bed {{ $bed->label() }}</div>
                                                    <div class="small text-muted">
                                                        {{ $bed->patient->name ?? ($bed->status === 'alloted' ? 'Occupied' : ucfirst($bed->status)) }}
                                                    </div>
                                                    @if ($bed->alloted_time)
                                                        <div class="small text-muted">
                                                            {{ \Illuminate\Support\Carbon::parse($bed->alloted_time)->format('d M, h:i A') }}
                                                        </div>
                                                    @endif

                                                    @if ($this->canAllocate())
                                                        @if ($bed->isAllocatable())
                                                            <div class="mt-2">
                                                                <select class="form-control form-control-sm mb-1"
                                                                    wire:model="allocatePatient.{{ $bed->id }}">
                                                                    <option value="">Patient&hellip;</option>
                                                                    @foreach ($patients as $p)
                                                                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                                                                    @endforeach
                                                                </select>
                                                                <button class="btn btn-sm btn-success btn-block"
                                                                    wire:click="allocate({{ $bed->id }})">
                                                                    Allocate
                                                                </button>
                                                            </div>
                                                        @else
                                                            <button class="btn btn-sm btn-outline-warning btn-block mt-2"
                                                                wire:click="release({{ $bed->id }})">
                                                                Release
                                                            </button>
                                                        @endif
                                                    @endif

                                                    @if ($this->canChangeStatus()) && $bed->status === 'cleaning')
                                                        <button class="btn btn-sm btn-outline-success btn-block mt-2"
                                                            wire:click="setStatus({{ $bed->id }}, 'available')">
                                                            Mark cleaned
                                                        </button>
                                                    @endif

                                                    @if ($this->canManage())
                                                        <div class="mt-2">
                                                            <button class="btn btn-outline-info btn-xs btn-rounded"
                                                                wire:click="show_edit_form({{ $bed->id }})">
                                                                <i class="fas fa-pen"></i>
                                                            </button>
                                                            <button class="btn btn-outline-danger btn-xs btn-rounded"
                                                                wire:click="delete({{ $bed->id }})"
                                                                onclick="return confirm('{{ __('Are You Sure ?') }}')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <p class="text-muted mb-0">No {{ strtolower($section['label']) }} set up yet.</p>
                        @endforelse
                    </div>
                </div>
            @empty
                <div class="box box-warning">
                    <div class="box-body">
                        <p class="mb-0">
                            No rooms yet. The Dean sets up rooms and bed numbers from this page.
                        </p>
                    </div>
                </div>
            @endforelse
        </div>
    </div>
</div>
