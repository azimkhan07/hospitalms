<div>
    <div class="row mb-2">
        <div class="col-md-8">
            <h4 class="mb-1"><i class="fas fa-truck text-info mr-1"></i> Home Deliveries</h4>
            <div class="d-flex flex-wrap align-items-center" style="gap:.3rem">
                <button type="button"
                    class="btn btn-xs {{ $statusFilter === '' ? 'btn-primary' : 'btn-outline-secondary' }}"
                    wire:click="$set('statusFilter', '')">
                    All <span class="badge badge-light ml-1">{{ $counts->sum() }}</span>
                </button>
                @foreach (['pending', 'assigned', 'out_for_delivery', 'delivered', 'cancelled'] as $s)
                    <button type="button"
                        class="btn btn-xs {{ $statusFilter === $s ? 'btn-primary' : 'btn-outline-secondary' }}"
                        wire:click="$set('statusFilter', '{{ $s }}')">
                        {{ ucwords(str_replace('_', ' ', $s)) }}
                        <span class="badge badge-light ml-1">{{ $counts[$s] ?? 0 }}</span>
                    </button>
                @endforeach
            </div>
        </div>
        <div class="col-md-4 text-md-right">
            <button type="button" class="btn btn-sm btn-primary" wire:click="toggleForm">
                <i class="fas fa-plus"></i> New Delivery
            </button>
        </div>
    </div>

    @if ($showForm)
        <div class="box box-primary mb-3">
            <div class="box-header">
                <h3 class="box-title"><i class="fas fa-truck-loading text-info mr-1"></i> New Delivery Order</h3>
            </div>
            <div class="box-body">
                <form wire:submit.prevent="createOrder">
                    <div class="form-row">
                        <div class="form-group col-md-5">
                            <label for="delPatient">Patient</label>
                            <input id="delPatient" type="search" class="form-control form-control-sm"
                                placeholder="Search by name or phone..." wire:model.live="patientSearch">
                            @error('patientId')
                                <span class="text-danger" style="font-size:11px">{{ $message }}</span>
                            @enderror
                            @if ($patientSearch !== '' && ! $patientId)
                                <div class="hms-participants mt-1">
                                    @forelse ($patients as $p)
                                        <button type="button" class="hms-pill"
                                            wire:click="pickPatient({{ $p->id }})">
                                            <i class="fas fa-user"></i>
                                            {{ $p->name }}
                                            <small>{{ $p->phone }}</small>
                                        </button>
                                    @empty
                                        <span class="text-muted" style="font-size:11.5px">No patients match.</span>
                                    @endforelse
                                </div>
                            @endif
                        </div>
                        <div class="form-group col-md-2">
                            <label for="delOrderType">Order Type</label>
                            <select id="delOrderType" class="form-control form-control-sm" wire:model="orderType">
                                <option value="medicine">Medicine</option>
                                <option value="lab">Lab sample</option>
                                <option value="general">General</option>
                            </select>
                        </div>
                        <div class="form-group col-md-5">
                            <label for="delPhone">Phone</label>
                            <input id="delPhone" type="text" class="form-control form-control-sm" wire:model="phone">
                            @error('phone')
                                <span class="text-danger" style="font-size:11px">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="delAddress">Delivery Address</label>
                        <textarea id="delAddress" rows="2" class="form-control form-control-sm" wire:model="address"></textarea>
                        @error('address')
                            <span class="text-danger" style="font-size:11px">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="delFee">Delivery Fee (Rs)</label>
                            <input id="delFee" type="number" min="0" step="0.01" class="form-control form-control-sm"
                                wire:model="deliveryFee">
                        </div>
                        <div class="form-group col-md-9">
                            <label for="delNote">Note</label>
                            <input id="delNote" type="text" class="form-control form-control-sm"
                                placeholder="e.g. Deliver with the afternoon prescriptions" wire:model="note">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fas fa-paper-plane"></i> Create Order
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="toggleForm">Cancel</button>
                </form>
            </div>
        </div>
    @endif

    <div class="box box-primary">
        <div class="box-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm hms-del-table" style="margin:0">
                    <thead>
                        <tr class="text-muted" style="font-size:11px">
                            <th>Order</th>
                            <th>Patient</th>
                            <th>Rider</th>
                            <th>Status</th>
                            <th>Fee</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            @php
                                $next = App\Services\DeliveryService::TRANSITIONS[$order->status] ?? [];
                            @endphp
                            <tr>
                                <td>
                                    <div class="d-flex flex-column" style="font-size:12px">
                                        <span>#{{ $order->id }}
                                            <span class="badge badge-light text-muted">{{ ucfirst($order->order_type) }}</span>
                                        </span>
                                        <span class="text-muted" style="font-size:10.5px">
                                            {{ $order->requested_at->format('d M, h:i A') }}
                                        </span>
                                    </div>
                                </td>
                                <td style="font-size:12px">
                                    <div class="d-flex flex-column">
                                        <span>{{ $order->patient?->name ?? 'Patient #'.$order->patient_id }}</span>
                                        <span class="text-muted" style="font-size:10.5px">{{ $order->phone }}</span>
                                        @if ($order->address)
                                            <span class="text-muted" style="font-size:10.5px" title="{{ $order->address }}">
                                                {{ \Illuminate\Support\Str::limit($order->address, 34) }}
                                            </span>
                                        @endif
                                        @if ($order->note)
                                            <span class="text-muted" style="font-size:10.5px"><i class="fas fa-sticky-note mr-1"></i>{{ $order->note }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td style="font-size:12px">
                                    @if ($order->rider_name)
                                        <div class="d-flex flex-column">
                                            <span><i class="fas fa-user mr-1"></i>{{ $order->rider_name }}</span>
                                            @if ($order->vehicle)
                                                <span class="text-muted" style="font-size:10.5px">{{ $order->vehicle }}</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-muted">Not assigned</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $badge = match ($order->status) {
                                            'pending' => 'badge-warning',
                                            'assigned' => 'badge-info',
                                            'out_for_delivery' => 'badge-primary',
                                            'delivered' => 'badge-success',
                                            default => 'badge-secondary',
                                        };
                                    @endphp
                                    <span class="badge {{ $badge }}">{{ $order->statusLabel() }}</span>
                                    @if ($order->delivered_at)
                                        <div class="text-muted" style="font-size:10.5px">{{ $order->delivered_at->format('d M, h:i A') }}</div>
                                    @endif
                                </td>
                                <td style="font-size:12px">{{ $order->delivery_fee > 0 ? 'Rs '.number_format((float) $order->delivery_fee, 2) : 'Free' }}</td>
                                <td class="text-right">
                                    @if ($canManage)
                                        @if ($order->status === 'pending')
                                            <div class="hms-del-actions">
                                                <input type="text" class="form-control form-control-sm hms-rider-inline" placeholder="Rider name"
                                                    wire:model="riderName" wire:key="rider-{{ $order->id }}">
                                                <button type="button" class="btn btn-xs btn-outline-info"
                                                    wire:click="assignRider({{ $order->id }})">Assign</button>
                                            </div>
                                        @elseif ($order->status === 'assigned')
                                            <button type="button" class="btn btn-xs btn-primary"
                                                onclick="return confirm('Mark out for delivery?')"
                                                wire:click="markOutForDelivery({{ $order->id }})">Dispatch</button>
                                        @elseif ($order->status === 'out_for_delivery')
                                            <button type="button" class="btn btn-xs btn-success"
                                                onclick="return confirm('Mark as delivered?')"
                                                wire:click="complete({{ $order->id }})">Delivered</button>
                                        @endif
                                    @endif
                                    @if ($canManage && in_array($order->status, ['pending', 'assigned'], true))
                                        <button type="button" class="btn btn-xs btn-outline-danger ml-1"
                                            onclick="return confirm('Cancel this delivery?')"
                                            wire:click="cancel({{ $order->id }})">Cancel</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-3 text-muted" style="font-size:12px">
                                No delivery orders {{ $statusFilter !== '' ? 'in '.ucwords(str_replace('_',' ',$statusFilter)) : '' }} yet.
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($orders->hasPages())
        <div class="mt-2">{{ $orders->links() }}</div>
    @endif

    <style>
        .hms-del-actions {
            display: inline-flex;
            gap: .35rem;
            align-items: center;
            max-width: 260px;
        }
        .hms-rider-inline {
            min-width: 120px;
            display: inline-block;
        }
        .hms-del-table > tbody > tr td { vertical-align: middle; }
    </style>
</div>