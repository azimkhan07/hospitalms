<div>
    @if ($show)
        <div class="card mt-3" style="border:1px solid #0f7fd4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong style="color:#0b3c66">
                <i class="fas fa-{{ $tenantId ? 'pen' : 'plus' }}"></i>
                {{ $tenantId ? 'Edit tenant' : 'New hospital / clinic' }}
            </strong>
            <button type="button" class="btn btn-sm btn-light" wire:click="close">&times;</button>
        </div>

        <div class="card-body">
            <ul class="nav nav-pills mb-3" style="font-size:13px">
                <li class="nav-item">
                    <span class="nav-link {{ $step === 1 ? 'active' : '' }}">1. Institution</span>
                </li>
                <li class="nav-item">
                    <span class="nav-link {{ $step === 2 ? 'active' : '' }}">2. Facilities &amp; Admin</span>
                </li>
            </ul>

            @if ($step === 1)
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label style="font-size:13px">Institution name *</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror"
                            wire:model.live="name" placeholder="e.g. Sunrise Multi-Speciality Hospital">
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6 form-group">
                        <label style="font-size:13px">Slug (URL key) *</label>
                        <input type="text" class="form-control @error('slug') is-invalid @enderror"
                            wire:model="slug">
                        @error('slug') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3 form-group">
                        <label style="font-size:13px">Mode *</label>
                        <select class="form-control" wire:model.live="mode">
                            @foreach ($modes as $key => $cfg)
                                <option value="{{ $key }}">{{ $cfg['label'] ?? ucfirst($key) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label style="font-size:13px">Status *</label>
                        <select class="form-control" wire:model="status">
                            <option value="active">Active</option>
                            <option value="trial">Trial</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label style="font-size:13px">Phone</label>
                        <input type="text" class="form-control" wire:model="phone">
                    </div>
                    <div class="col-md-3 form-group">
                        <label style="font-size:13px">Email</label>
                        <input type="email" class="form-control" wire:model="email">
                    </div>

                    <div class="col-md-6 form-group">
                        <label style="font-size:13px">Address</label>
                        <input type="text" class="form-control" wire:model="address">
                    </div>
                    <div class="col-md-3 form-group">
                        <label style="font-size:13px">City</label>
                        <input type="text" class="form-control" wire:model="city">
                    </div>
                    <div class="col-md-3 form-group">
                        <label style="font-size:13px">Country</label>
                        <input type="text" class="form-control" wire:model="country">
                    </div>

                    <div class="col-md-6 form-group">
                        <label style="font-size:13px">Working hours</label>
                        <input type="text" class="form-control" wire:model="working_hours"
                            placeholder="OPD 8:00 AM - 8:00 PM">
                    </div>
                    <div class="col-md-3 form-group">
                        <label style="font-size:13px">Domain (optional)</label>
                        <input type="text" class="form-control" wire:model="domain"
                            placeholder="hospital.example.com">
                        @error('domain') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="col-md-3 form-group">
                        <label style="font-size:13px">Subdomain (optional)</label>
                        <input type="text" class="form-control" wire:model="subdomain"
                            placeholder="sunrise">
                        @error('subdomain') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>

                    <div class="col-md-6 form-group">
                        <label style="font-size:13px">Logo</label>
                        <input type="file" class="form-control-file" wire:model="logo" accept="image/*">
                        @if ($existingLogo)
                            <small class="text-muted">Current: {{ $existingLogo }}</small>
                        @endif
                        @error('logo') <small class="text-danger d-block">{{ $message }}</small> @enderror
                    </div>
                    <div class="col-md-6 form-group">
                        <label style="font-size:13px">Hero image</label>
                        <input type="file" class="form-control-file" wire:model="hero_image" accept="image/*">
                        @if ($existingHero)
                            <small class="text-muted">Current: {{ $existingHero }}</small>
                        @endif
                        @error('hero_image') <small class="text-danger d-block">{{ $message }}</small> @enderror
                    </div>
                </div>
            @else
                <div class="row">
                    @if ($mode === 'hospital')
                        <div class="col-md-3 form-group">
                            <label style="font-size:13px">Beds</label>
                            <input type="number" class="form-control" wire:model="beds" min="0">
                        </div>
                        <div class="col-md-3 form-group">
                            <label style="font-size:13px">Floors</label>
                            <input type="number" class="form-control" wire:model="floors" min="0">
                        </div>
                    @endif
                    <div class="col-md-3 form-group">
                        <label style="font-size:13px">Staff count</label>
                        <input type="number" class="form-control" wire:model="staff" min="0">
                    </div>

                    <div class="col-md-6 form-group">
                        <label style="font-size:13px">Departments (comma separated)</label>
                        <input type="text" class="form-control" wire:model="departments"
                            placeholder="Cardiology, Neurology, Orthopaedics">
                    </div>
                    <div class="col-md-6 form-group">
                        <label style="font-size:13px">Services (comma separated)</label>
                        <input type="text" class="form-control" wire:model="services"
                            placeholder="Emergency, ICU, Maternity">
                    </div>

                    @if ($mode === 'hospital')
                        <div class="col-md-12 form-group">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="has_lab" wire:model="has_lab">
                                <label class="form-check-label" for="has_lab" style="font-size:13px">Laboratory</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="has_ot" wire:model="has_ot">
                                <label class="form-check-label" for="has_ot" style="font-size:13px">Operation theatre</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="has_ambulance"
                                    wire:model="has_ambulance">
                                <label class="form-check-label" for="has_ambulance"
                                    style="font-size:13px">Ambulance</label>
                            </div>
                        </div>
                    @endif

                    <div class="col-md-12">
                        <hr>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="create_admin"
                                wire:model.live="create_admin">
                            <label class="form-check-label" for="create_admin" style="font-size:13px">
                                <strong>{{ $tenantId ? 'Reset / create admin' : 'Create the first admin' }}</strong>
                            </label>
                        </div>

                        @if ($create_admin)
                            <div class="row">
                                <div class="col-md-4 form-group">
                                    <label style="font-size:13px">Admin name *</label>
                                    <input type="text" class="form-control @error('admin_name') is-invalid @enderror"
                                        wire:model="admin_name">
                                    @error('admin_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4 form-group">
                                    <label style="font-size:13px">Admin email *</label>
                                    <input type="email" class="form-control @error('admin_email') is-invalid @enderror"
                                        wire:model="admin_email">
                                    @error('admin_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4 form-group">
                                    <label style="font-size:13px">Password *</label>
                                    <input type="text" class="form-control @error('admin_password') is-invalid @enderror"
                                        wire:model="admin_password" placeholder="min 6 characters">
                                    @error('admin_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <div class="d-flex justify-content-between mt-3">
                <div>
                    @if ($step === 2)
                        <button type="button" class="btn btn-light btn-sm" wire:click="back">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                    @endif
                </div>
                <div>
                    <button type="button" class="btn btn-light btn-sm" wire:click="close">Cancel</button>
                    @if ($step === 1)
                        <button type="button" class="btn btn-primary btn-sm" wire:click="next">
                            Next <i class="fas fa-arrow-right"></i>
                        </button>
                    @else
                        <button type="button" class="btn btn-primary btn-sm" wire:click="save"
                            wire:loading.attr="disabled">
                            <i class="fas fa-save"></i>
                            {{ $tenantId ? 'Update tenant' : 'Create tenant' }}
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
