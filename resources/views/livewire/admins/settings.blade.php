<div class="content">
    <div class="container">
        <div class="page-title d-flex align-items-center justify-content-between flex-wrap">
            <h3 class="text-info">General Settings</h3>
            <span class="badge badge-info">Mode: {{ hms_institution_label() }}</span>
        </div>

        <div class="box box-primary">
            <div class="box-body">
                <div class="text-info" wire:loading>Loading..</div>

                @if (session()->has('message'))
                    <div class="alert alert-success py-1 px-2">{{ session('message') }}</div>
                @endif

                <form wire:submit.prevent="updateSettings" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-lg-6">
                            <h5 class="text-info border-bottom pb-1 mb-2">Branding</h5>

                            @foreach (['title', 'tagline'] as $key)
                                <div class="form-group mb-2">
                                    <label class="mb-1" style="font-size:11px">{{ ucfirst(str_replace('_', ' ', $key)) }}</label>
                                    <input type="text" wire:model="settings.{{ $key }}" class="form-control form-control-sm">
                                </div>
                            @endforeach

                            <div class="form-group mb-2">
                                <label class="mb-1" style="font-size:11px">Institution Mode</label>
                                <select wire:model.live="settings.institution_mode" class="form-control form-control-sm">
                                    @foreach ($modes as $slug => $mode)
                                        <option value="{{ $slug }}">{{ $mode['label'] ?? ucfirst($slug) }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted" style="font-size:10.5px">
                                    Switching to Clinic restricts logins to admin, receptionist, doctor and pharmacist and hides hospital-only modules.
                                </small>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <h5 class="text-info border-bottom pb-1 mb-2">Contact</h5>

                            @foreach (['address', 'phone', 'business_phone', 'email', 'business_email', 'working_hours'] as $key)
                                @if (array_key_exists($key, $settings))
                                    <div class="form-group mb-2">
                                        <label class="mb-1" style="font-size:11px">{{ ucfirst(str_replace('_', ' ', $key)) }}</label>
                                        <input type="text" wire:model="settings.{{ $key }}" class="form-control form-control-sm">
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    <h5 class="text-info border-bottom pb-1 mb-2 mt-2">Landing Page Content</h5>
                    <div class="row">
                        <div class="col-lg-6">
                            @foreach (['hero_title', 'hero_subtitle', 'about_title', 'emergency_title', 'emergency_text', 'description', 'doctors_subtitle'] as $key)
                                @if (array_key_exists($key, $settings))
                                    <div class="form-group mb-2">
                                        <label class="mb-1" style="font-size:11px">{{ ucfirst(str_replace('_', ' ', $key)) }}</label>
                                        @if (in_array($key, $textareaKeys, true))
                                            <textarea rows="2" wire:model="settings.{{ $key }}" class="form-control form-control-sm"></textarea>
                                        @else
                                            <input type="text" wire:model="settings.{{ $key }}" class="form-control form-control-sm">
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        <div class="col-lg-6">
                            @foreach ($fileKeys as $key)
                                <div class="form-group mb-2">
                                    <label class="mb-1" style="font-size:11px">{{ ucfirst(str_replace('_', ' ', $key)) }}</label>
                                    <input type="file" wire:model="{{ $key }}" class="form-control-file form-control-sm"
                                        accept="image/*">
                                    @error($key)
                                        <span class="text-danger d-block" style="font-size:10.5px">{{ $message }}</span>
                                    @enderror
                                    <div wire:loading wire:target="{{ $key }}" class="text-muted" style="font-size:10.5px">
                                        Uploading...
                                    </div>
                                    @php($current = $settings[$key] ?? null)
                                    @if ($this->{$key})
                                        <img src="{{ $this->{$key}->temporaryUrl() }}" class="mt-1 rounded" style="height:44px" alt="preview">
                                    @elseif ($current)
                                        <img src="{{ storage_url($current) }}" class="mt-1 rounded" style="height:44px" alt="{{ $key }}">
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <h5 class="text-info border-bottom pb-1 mb-2 mt-2">Section Headings &amp; Labels</h5>
                    <div class="row">
                        @foreach (['fact_working_title', 'fact_departments_title', 'services_heading', 'services_page_heading', 'doctors_heading', 'testimonials_heading', 'contact_heading', 'contact_card1_title', 'contact_card2_title', 'contact_card3_title', 'about_heading', 'about_sub_heading', 'about_intro', 'hero_btn1', 'hero_btn2', 'about_btn', 'footer_contact_title', 'copyright_text'] as $key)
                            @if (array_key_exists($key, $settings))
                                <div class="col-lg-4">
                                    <div class="form-group mb-2">
                                        <label class="mb-1" style="font-size:11px">{{ ucfirst(str_replace('_', ' ', $key)) }}</label>
                                        <input type="text" wire:model="settings.{{ $key }}" class="form-control form-control-sm">
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                    <p class="text-muted" style="font-size:10.5px">
                        Feature cards and patient testimonials (quotes, names, photos) are managed from the Features and Testimonials pages.
                    </p>

                    <h5 class="text-info border-bottom pb-1 mb-2 mt-2">Social Links</h5>
                    <div class="row">
                        @foreach (['facebook', 'twitter', 'instagram', 'linkedin', 'youtube', 'pinterest'] as $key)
                            @if (array_key_exists($key, $settings))
                                <div class="col-lg-4">
                                    <div class="form-group mb-2">
                                        <label class="mb-1" style="font-size:11px">{{ ucfirst($key) }}</label>
                                        <input type="text" wire:model="settings.{{ $key }}" class="form-control form-control-sm">
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>

                    <button type="submit" class="btn btn-sm btn-primary mt-2">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
