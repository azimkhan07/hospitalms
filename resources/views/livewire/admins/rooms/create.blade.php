<div>
    <div class="content">
        <div class="container">
            <div class="page-title">
                <h3 class="text-info">{{ env('APP_NAME') }} Rooms</h3>
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
            </div>
            <div class="box box-primary">
                <div class="box-body">
                    <div class="text-info" wire:loading>Loading..</div>
                    <form accept-charset="utf-8" class="shadow rounded p-3" wire:submit.prevent="add_room()">
                        <div class="text-capitalize bg-dark p-2 shadow mb-3 text-center text-lg text-light rounded">
                            {{ __('Add New room') }}</div>
                        <div class="form-group">
                            <label for="name">Room name / number</label>
                            <input type="text" name="name" wire:model.lazy="name" class="form-control" required
                                placeholder="General Ward A / ICU / Private 1">
                            @error('name')
                                <span class="text-red-500 text-danger text-xs">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="department">Department</label>
                            <select name="department" wire:model.lazy="department" class="form-control" required>
                                <option selected value="">Choose Department</option>
                                @forelse ($departments as $department)
                                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                                @empty
                                    <option value="">Null</option>
                                @endforelse
                            </select>

                            @error('department')
                                <span class="text-red-500 text-danger text-xs">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="type">Room Type</label>
                            <select required wire:model.lazy="type" class="form-control" name="type"
                                id="">
                                <option value="">Select Type</option>
                                <option value="general">General Ward</option>
                                <option value="ward">Ward</option>
                                <option value="icu">ICU</option>
                                @if ($privateEnabled)
                                    <option value="private">Private</option>
                                    <option value="semi-private">Semi-Private</option>
                                @endif
                            </select>
                            @if (! $privateEnabled)
                                <small class="text-muted">
                                    Private rooms are switched off for this facility.
                                </small>
                            @elseif ($privateQuota)
                                <small class="text-muted">
                                    The facility declared {{ $privateQuota }} private room(s) at creation.
                                </small>
                            @endif
                            @error('type')
                                <span class="text-red-500 text-danger text-xs">{{ $message }}</span>
                            @enderror
                        </div>
                        @if ($type !== 'private')
                            <div class="form-group">
                                <label for="capacity">Number of beds</label>
                                <input type="number" min="1" max="200" name="capacity" wire:model.lazy="capacity" class="form-control"
                                    required>
                                <small class="text-muted">
                                    Each bed is created with a number (G1, G2, ICU1, ...).
                                </small>
                                @error('capacity')
                                    <span class="text-red-500 text-danger text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                        @endif
                        <div class="form-group">
                            <label for="floor">Floor</label>
                            <input type="text" name="floor" wire:model.lazy="floor" class="form-control" placeholder="1">
                            @error('floor')
                                <span class="text-red-500 text-danger text-xs">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="daily_rate">Rate per day</label>
                            <input type="number" min="0" step="0.01" name="daily_rate" wire:model.lazy="daily_rate" class="form-control">
                            @error('daily_rate')
                                <span class="text-red-500 text-danger text-xs">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select required wire:model.lazy="status" class="form-control" name="status"
                                id="">
                                <option value="">Select Status</option>
                                <option value="available">Available</option>
                                <option value="occupied">Occupied</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                            @error('status')
                                <span class="text-red-500 text-danger text-xs">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <input type="submit" class="btn btn-primary" value="{{ $button_text }}">
                        </div>
                    </form><br>
                </div>
            </div>
        </div>

