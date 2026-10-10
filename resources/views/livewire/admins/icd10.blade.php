<div class="content">
    <div class="container">
        <div class="page-title d-flex align-items-center justify-content-between flex-wrap">
            <h3 class="text-info"><i class="fas fa-diagnoses mr-1"></i> ICD-10 Diagnosis Codes</h3>
            <button class="btn btn-sm btn-outline-primary" wire:click="create">
                <i class="fas fa-plus"></i> Add code
            </button>
        </div>

        @if (session()->has('message'))
            <div class="alert alert-success py-1 px-2">{{ session('message') }}</div>
        @endif
        @if (session()->has('error'))
            <div class="alert alert-danger py-1 px-2">{{ session('error') }}</div>
        @endif

        @if ($showForm)
            <div class="box box-primary">
                <div class="box-header"><h3 class="box-title" style="font-size:13px">
                    {{ $editingId ? 'Edit code' : 'New code' }}
                </h3></div>
                <div class="box-body">
                    <form wire:submit.prevent="save">
                        <div class="form-row">
                            <div class="form-group col-md-2">
                                <label>Code</label>
                                <input type="text" class="form-control form-control-sm" wire:model="code" maxlength="10" placeholder="e.g. J06.9">
                                @error('code') <span class="text-danger" style="font-size:10.5px">{{ $message }}</span> @enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label>Description</label>
                                <input type="text" class="form-control form-control-sm" wire:model="description" maxlength="300" placeholder="Diagnosis description">
                                @error('description') <span class="text-danger" style="font-size:10.5px">{{ $message }}</span> @enderror
                            </div>
                            <div class="form-group col-md-3">
                                <label>Chapter</label>
                                <input type="text" class="form-control form-control-sm" wire:model="chapter" maxlength="120" placeholder="Optional">
                                @error('chapter') <span class="text-danger" style="font-size:10.5px">{{ $message }}</span> @enderror
                            </div>
                            <div class="form-group col-md-1 d-flex align-items-end">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="icdActive" wire:model="isActive">
                                    <label class="custom-control-label" for="icdActive" style="font-size:12px">Active</label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary mr-1"><i class="fas fa-save"></i> Save</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="cancel">Cancel</button>
                    </form>
                </div>
            </div>
        @endif

        <div class="box box-primary">
            <div class="box-header d-flex align-items-center justify-content-between flex-wrap">
                <h3 class="box-title" style="font-size:13px">
                    <i class="fas fa-list text-info mr-1"></i> Directory
                    <span class="badge badge-info">{{ $codes->total() }}</span>
                </h3>
                <input type="search" class="form-control form-control-sm" style="max-width:240px"
                    placeholder="Search code or description..." wire:model.live.debounce.300ms="search">
            </div>
            <div class="box-body">
                <table class="table table-sm table-bordered mb-0" style="font-size:12.5px">
                    <thead>
                        <tr>
                            <th style="width:90px">Code</th>
                            <th>Description</th>
                            <th style="width:220px">Chapter</th>
                            <th style="width:90px">Status</th>
                            <th style="width:130px" class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($codes as $row)
                            <tr class="{{ $row->is_active ? '' : 'text-muted' }}">
                                <td><b>{{ $row->code }}</b></td>
                                <td>{{ $row->description }}</td>
                                <td><small>{{ $row->chapter ?? '-' }}</small></td>
                                <td>
                                    <span class="badge badge-{{ $row->is_active ? 'success' : 'secondary' }}">
                                        {{ $row->is_active ? 'Active' : 'Archived' }}
                                    </span>
                                </td>
                                <td class="text-right">
                                    <button class="btn btn-xs btn-outline-primary" wire:click="edit({{ $row->id }})" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-xs btn-outline-{{ $row->is_active ? 'danger' : 'success' }}"
                                        wire:click="toggleActive({{ $row->id }})" title="{{ $row->is_active ? 'Archive' : 'Restore' }}">
                                        <i class="fas fa-{{ $row->is_active ? 'archive' : 'undo' }}"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">No codes match.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                @if ($codes->hasPages())
                    <div class="mt-2">{{ $codes->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
