<div>
    <div class="content">
        <div class="container">
            <div class="row page-title row">
                <div class="col">
                    <h3 class="text-info">{{ env('APP_NAME') }} Rooms</h3>
                </div>
                <div class="col-auto">
                    @if ($this->canManage())
                        <button class="btn btn-primary" wire:click="show_create_form">Add New</button>
                    @else
                        <span class="badge badge-secondary">Read-only &mdash; the Dean sets up rooms</span>
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

            @php
                $labels = ['general' => 'General Ward', 'ward' => 'Ward', 'icu' => 'ICU', 'private' => 'Private Rooms', 'semi-private' => 'Semi-Private Rooms'];
            @endphp

            @forelse ($sections as $type => $typeRooms)
                <div class="box box-primary">
                    <div class="box-body">
                        <div class="text-capitalize bg-dark p-2 shadow mb-3 text-light rounded">
                            {{ $labels[$type] ?? ucfirst($type) }}
                            <span class="badge badge-light ml-1">{{ $typeRooms->sum('beds_count') }} beds</span>
                        </div>

                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th class="text-center">Room</th>
                                    <th class="text-center">Type</th>
                                    <th class="text-center">Floor</th>
                                    <th class="text-center">Beds</th>
                                    <th class="text-center">Rate / day</th>
                                    <th class="text-center">Status</th>
                                    @if ($this->canManage())
                                        <th class="text-center">Actions</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($typeRooms as $room)
                                    <tr>
                                        <td class="text-center">{{ $room->name }}</td>
                                        <td class="text-center">{{ ucfirst(str_replace('-', ' ', $room->type)) }}</td>
                                        <td class="text-center">{{ $room->floor ?? '--' }}</td>
                                        <td class="text-center">{{ $room->beds_count }}</td>
                                        <td class="text-center">
                                            {{ $room->daily_rate !== null ? number_format((float) $room->daily_rate, 0) : '--' }}
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-{{ $room->status === 'available' ? 'success' : ($room->status === 'occupied' ? 'danger' : 'warning') }}">
                                                {{ ucfirst($room->status) }}
                                            </span>
                                        </td>
                                        @if ($this->canManage())
                                            <td class="text-center">
                                                <button wire:click="show_edit_form({{ $room->id }})"
                                                    class="btn btn-outline-info btn-rounded btn-xs">
                                                    <i class="fas fa-pen"></i>
                                                </button>
                                                <button wire:click="delete({{ $room->id }})"
                                                    onclick="return confirm('{{ __('Are You Sure ?') }}')"
                                                    class="btn btn-outline-danger btn-rounded btn-xs">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="box box-warning">
                    <div class="box-body">
                        <p class="mb-0">No rooms set up yet.</p>
                    </div>
                </div>
            @endforelse
        </div>
    </div>
</div>
