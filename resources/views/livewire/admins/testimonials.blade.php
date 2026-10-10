<div class="content">
    <div class="container">
        <div class="page-title d-flex align-items-center justify-content-between flex-wrap">
            <h3 class="text-info">Patient Testimonials</h3>
            @if ($_page === 'index')
                <button type="button" class="btn btn-sm btn-primary" wire:click="show_create_form">
                    <i class="fas fa-plus"></i> Add Testimonial
                </button>
            @else
                <button type="button" class="btn btn-sm btn-secondary" wire:click="cancel">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
            @endif
        </div>

        @if (session()->has('message'))
            <div class="alert alert-success py-1 px-2">{{ session('message') }}</div>
        @endif

        @if ($_page !== 'index')
            <div class="box box-primary">
                <div class="box-body">
                    <form wire:submit.prevent="store" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-lg-4">
                                <div class="form-group mb-2">
                                    <label class="mb-1" style="font-size:11px">Patient name</label>
                                    <input type="text" wire:model="name" class="form-control form-control-sm">
                                    @error('name')<span class="text-danger d-block" style="font-size:10.5px">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="form-group mb-2">
                                    <label class="mb-1" style="font-size:11px">Role / department</label>
                                    <input type="text" wire:model="role" class="form-control form-control-sm">
                                    @error('role')<span class="text-danger d-block" style="font-size:10.5px">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="form-group mb-2">
                                    <label class="mb-1" style="font-size:11px">Sort order</label>
                                    <input type="number" wire:model="sort_order" min="0" class="form-control form-control-sm">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group mb-2">
                                    <label class="mb-1" style="font-size:11px">Quote</label>
                                    <textarea wire:model="quote" rows="3" class="form-control form-control-sm"></textarea>
                                    @error('quote')<span class="text-danger d-block" style="font-size:10.5px">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group mb-2">
                                    <label class="mb-1" style="font-size:11px">Photo (optional)</label>
                                    <input type="file" wire:model="photo" class="form-control-file form-control-sm" accept="image/*">
                                    @error('photo')<span class="text-danger d-block" style="font-size:10.5px">{{ $message }}</span>@enderror
                                    @if ($photo)
                                        <img src="{{ $photo->temporaryUrl() }}" class="mt-1 rounded" style="height:44px" alt="preview">
                                    @endif
                                </div>
                                <div class="form-group mb-2">
                                    <label class="mb-1 d-block" style="font-size:11px">Active</label>
                                    <select wire:model="active" class="form-control form-control-sm">
                                        <option value="1">Yes</option>
                                        <option value="0">No</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary mt-1">
                            <i class="fas fa-save"></i> {{ $edit_id ? 'Update Testimonial' : 'Save Testimonial' }}
                        </button>
                    </form>
                </div>
            </div>
        @endif

        <div class="box box-primary">
            <div class="box-body">
                <p class="text-muted" style="font-size:10.5px">
                    These quotes, names and photos appear in the "What Our Patients Say" section on the homepage.
                </p>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width:40px">#</th>
                                <th>Photo</th>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Quote</th>
                                <th style="width:60px">Order</th>
                                <th style="width:70px">Status</th>
                                <th style="width:160px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($testimonials as $item)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>
                                        @if ($item->photo)
                                            <img src="{{ storage_url($item->photo) }}" class="rounded-circle" style="height:32px;width:32px;object-fit:cover" alt="photo">
                                        @else
                                            <span class="text-muted">none</span>
                                        @endif
                                    </td>
                                    <td>{{ $item->name }}</td>
                                    <td>{{ $item->role }}</td>
                                    <td>{{ $item->quote }}</td>
                                    <td>{{ $item->sort_order }}</td>
                                    <td>
                                        <button type="button" wire:click="toggle({{ $item->id }})"
                                            class="btn btn-xs {{ $item->active ? 'btn-success' : 'btn-secondary' }}">
                                            {{ $item->active ? 'Active' : 'Hidden' }}
                                        </button>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-xs btn-info" wire:click="edit({{ $item->id }})">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-xs btn-danger"
                                            wire:click="delete({{ $item->id }})" wire:confirm="Delete this testimonial?">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted py-2">No testimonials yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>