<div class="content">
    <div class="container">
        <div class="page-title d-flex align-items-center justify-content-between flex-wrap">
            <h3 class="text-info">Website Features</h3>
            @if ($_page === 'index')
                <button type="button" class="btn btn-sm btn-primary" wire:click="show_create_form">
                    <i class="fas fa-plus"></i> Add Feature
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
                                    <label class="mb-1" style="font-size:11px">Title</label>
                                    <input type="text" wire:model="title" class="form-control form-control-sm">
                                    @error('title')<span class="text-danger d-block" style="font-size:10.5px">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <div class="col-lg-8">
                                <div class="form-group mb-2">
                                    <label class="mb-1" style="font-size:11px">Short description</label>
                                    <input type="text" wire:model="text" class="form-control form-control-sm">
                                    @error('text')<span class="text-danger d-block" style="font-size:10.5px">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <div class="col-lg-3">
                                <div class="form-group mb-2">
                                    <label class="mb-1" style="font-size:11px">Sort order</label>
                                    <input type="number" wire:model="sort_order" min="0" class="form-control form-control-sm">
                                </div>
                            </div>
                            <div class="col-lg-3">
                                <div class="form-group mb-2">
                                    <label class="mb-1 d-block" style="font-size:11px">Active</label>
                                    <select wire:model="active" class="form-control form-control-sm">
                                        <option value="1">Yes</option>
                                        <option value="0">No</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group mb-2">
                                    <label class="mb-1" style="font-size:11px">Icon image</label>
                                    <input type="file" wire:model="icon" class="form-control-file form-control-sm" accept="image/*">
                                    @error('icon')<span class="text-danger d-block" style="font-size:10.5px">{{ $message }}</span>@enderror
                                    @if ($icon)
                                        <img src="{{ $icon->temporaryUrl() }}" class="mt-1 rounded" style="height:44px" alt="preview">
                                    @endif
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary mt-1">
                            <i class="fas fa-save"></i> {{ $edit_id ? 'Update Feature' : 'Save Feature' }}
                        </button>
                    </form>
                </div>
            </div>
        @endif

        <div class="box box-primary">
            <div class="box-body">
                <p class="text-muted" style="font-size:10.5px">
                    These cards appear under the Services page booking form and power the "Why us" tiles.
                </p>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width:40px">#</th>
                                <th>Icon</th>
                                <th>Title</th>
                                <th>Description</th>
                                <th style="width:60px">Order</th>
                                <th style="width:70px">Status</th>
                                <th style="width:160px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($features as $feature)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>
                                        @if ($feature->icon)
                                            <img src="{{ storage_url($feature->icon) }}" class="rounded" style="height:32px;width:32px;object-fit:cover" alt="icon">
                                        @else
                                            <span class="text-muted">default</span>
                                        @endif
                                    </td>
                                    <td>{{ $feature->title }}</td>
                                    <td>{{ $feature->text }}</td>
                                    <td>{{ $feature->sort_order }}</td>
                                    <td>
                                        <button type="button" wire:click="toggle({{ $feature->id }})"
                                            class="btn btn-xs {{ $feature->active ? 'btn-success' : 'btn-secondary' }}">
                                            {{ $feature->active ? 'Active' : 'Hidden' }}
                                        </button>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-xs btn-info" wire:click="edit({{ $feature->id }})">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-xs btn-danger"
                                            wire:click="delete({{ $feature->id }})" wire:confirm="Delete this feature?">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-2">No features yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>