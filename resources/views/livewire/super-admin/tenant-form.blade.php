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
                    <span class="nav-link {{ $step === 2 ? 'active' : '' }}">2. Facilities</span>
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
                    {{-- Type sits on step 1 because it is the facility's identity
                         and it is what suggests the roles on step 2. --}}
                    <div class="col-md-3 form-group">
                        <label style="font-size:13px">What kind of facility is this? *</label>
                        <select class="form-control" wire:model.live="clinic_type_id">
                            <option value="">Select a type</option>
                            @foreach ($clinicTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">A dental clinic has no ward; a skin clinic has no laboratory.</small>
                        @error('clinic_type_id') <small class="text-danger d-block">{{ $message }}</small> @enderror
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
                    <div class="col-md-6 form-group">
                        <label style="font-size:13px">Country</label>
                        <select id="country-select" class="form-control" wire:model="country" data-init="{{ $country }}">
                            <option value="">-- Select Country --</option>
                        </select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label style="font-size:13px">State / Province</label>
                        <select id="state-select" class="form-control" data-init="{{ $state ?? '' }}" disabled>
                            <option value="">-- Select State/Province --</option>
                        </select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label style="font-size:13px">City</label>
                        <select id="city-select" class="form-control" data-init="{{ $city }}">
                            <option value="">-- Select City --</option>
                        </select>
                        <input type="hidden" id="city-input" wire:model="city" data-init="{{ $city }}">
                    </div>

                    <div class="col-md-6 form-group">
                        <label style="font-size:13px">Website address</label>
                        <div class="d-flex" style="gap:.9rem">
                            <label class="mb-0" style="font-weight:400;font-size:12.5px">
                                <input type="radio" value="auto" wire:model.live="domain_mode"> Auto
                            </label>
                            <label class="mb-0" style="font-weight:400;font-size:12.5px">
                                <input type="radio" value="custom" wire:model.live="domain_mode"> Own domain
                            </label>
                        </div>
                        @if ($domain_mode === 'custom')
                            <input type="text" class="form-control form-control-sm mt-1" wire:model="custom_domain"
                                placeholder="hms.apollohospital.com">
                            <small class="text-muted">The domain the facility already owns / purchased.</small>
                            @error('custom_domain') <small class="text-danger d-block">{{ $message }}</small> @enderror
                        @else
                            <input type="text" class="form-control form-control-sm mt-1 bg-light"
                                value="{{ $autoSubdomain }}" disabled>
                            <small class="text-muted">
                                Auto-created from the name. Set
                                <code>HMS_BASE_DOMAIN</code> to change the base.
                            </small>
                        @endif
                    </div>
                    <div class="col-md-6 form-group">
                        <label style="font-size:13px">Working hours</label>
                        <input type="text" class="form-control form-control-sm" wire:model="working_hours"
                            placeholder="OPD 8:00 AM - 8:00 PM">
                    </div>
                    @if ($domain_mode === 'auto')
                        <div class="col-md-6 form-group">
                            <label style="font-size:13px">Subdomain label (optional)</label>
                            <input type="text" class="form-control form-control-sm" wire:model="subdomain"
                                placeholder="defaults to the slug">
                            @error('subdomain') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                    @endif

                    <div class="col-md-6 form-group">
                        <label style="font-size:13px">Logo</label>
                        <input type="file" class="form-control-file" wire:model="logo" accept="image/*">
                        @if ($existingLogo)
                            <small class="text-muted">Current: {{ $existingLogo }}</small>
                        @endif
                        @error('logo') <small class="text-danger d-block">{{ $message }}</small> @enderror
                    </div>

                    <div class="col-12 form-group">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <label style="font-size:13px">Hospital location</label>
                            <button type="button" class="btn btn-xs btn-outline-primary"
                                wire:click="useCurrentLocation">
                                <i class="fas fa-crosshairs"></i> Use my current location
                            </button>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <input type="number" step="0.0000001" class="form-control" placeholder="Latitude"
                                    wire:model="latitude">
                                @error('latitude') <small class="text-danger d-block">{{ $message }}</small> @enderror
                            </div>
                            <div class="col-md-4">
                                <input type="number" step="0.0000001" class="form-control" placeholder="Longitude"
                                    wire:model="longitude">
                                @error('longitude') <small class="text-danger d-block">{{ $message }}</small> @enderror
                            </div>
                            <div class="col-md-4">
                                <div class="input-group">
                                    <input type="number" min="20" max="5000" class="form-control"
                                        placeholder="Allowed radius" wire:model="geo_radius_meters">
                                    <div class="input-group-append">
                                        <span class="input-group-text">m</span>
                                    </div>
                                </div>
                                @error('geo_radius_meters') <small class="text-danger d-block">{{ $message }}</small> @enderror
                            </div>
                        </div>
                        <small class="text-muted d-block mt-1">
                            Staff may only sign in from inside this radius of the hospital. The tenant admin
                            is exempt and may sign in from anywhere. Leave both coordinates empty to let
                            everyone sign in from anywhere.
                        </small>
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

                        <!-- Private rooms (PLAN.md §9b): Yes / No at hospital creation -->
                        <div class="col-md-4 form-group">
                            <label style="font-size:13px">Do you provide private rooms?</label>
                            <select class="form-control" wire:model.live="private_room_enabled">
                                <option value="0">No</option>
                                <option value="1">Yes</option>
                            </select>
                            @error('private_room_enabled') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        @if ((bool)$private_room_enabled)
                            <div class="col-md-4 form-group">
                                <label style="font-size:13px">How many private rooms?</label>
                                <input type="number" class="form-control" wire:model="private_room_count"
                                    min="1" max="999" placeholder="e.g. 2">
                                <small class="text-muted">Private rooms are listed in their own section with one bed each.</small>
                                @error('private_room_count') <small class="text-danger d-block">{{ $message }}</small> @enderror
                            </div>
                        @endif
                    @endif

                    {{-- The roles this facility needs (PLAN.md §9c.2); type was chosen on step 1 --}}
                    <div class="col-12 form-group">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <label style="font-size:13px; margin:0">Which roles does this facility need? *</label>
                            @if ($clinic_type_id)
                                <span class="text-muted" style="font-size:12px">
                                    {{ $clinicTypes->firstWhere('id', (int) $clinic_type_id)?->name ?? 'Custom' }}
                                </span>
                                <button type="button" class="btn btn-link btn-sm p-0" style="font-size:12px"
                                    wire:click="useSuggestedRoles">Reset to suggested</button>
                            @endif
                        </div>

                        <div class="row">
                            @foreach ($modeRoleSlugs as $slug)
                                <div class="col-md-3 col-6 mb-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                            id="role-{{ $slug }}"
                                            value="{{ $slug }}"
                                            wire:model.live="requiredRoles">
                                        <label class="form-check-label" for="role-{{ $slug }}"
                                            style="font-size:13px">{{ $roleLabels[$slug] ?? $slug }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <small class="text-muted">
                            This list drives the facility's menu, its staff form and what its Dean may manage.
                            Nothing outside it can be reached in this tenant.
                        </small>
                        @error('requiredRoles') <small class="text-danger d-block">{{ $message }}</small> @enderror
                        @error('requiredRoles.*') <small class="text-danger d-block">{{ $message }}</small> @enderror
                    </div>

                    @push('scripts')
                        <script src="{{ asset('js/country-state-city.js') }}"></script>
                        <script src="{{ asset('js/country-state-city-dropdown.js') }}"></script>
                    @endpush
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

@script
    <script>
        // "Use my current location" reads the browser's GPS instead of making
        // the platform type coordinates in by hand.
        window.addEventListener('capture-location', () => {
            if (! navigator.geolocation) {
                alert('This browser cannot read your location.');
                return;
            }
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    // Values must go through the Livewire property, not by
                    // assigning to a Blade-rendered literal (that compiles to
                    // something like "null = ..." and throws in the browser).
                    $wire.set('latitude', pos.coords.latitude);
                    $wire.set('longitude', pos.coords.longitude);
                },
                () => alert('Could not read your location. Allow location access and try again.')
            );
        });
    </script>
@endscript
