<div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 style="font-size:17px;margin:0;color:#0b3c66">Website Themes</h2>
            <p style="font-size:13px;color:#61748a;margin:2px 0 0">
                Sell a public website design to a facility: record the payment, unlock the theme, switch it on.
            </p>
        </div>
    </div>

    @if (session('sanotice'))
        <div class="alert alert-success py-2" style="font-size:13px">{{ session('sanotice') }}</div>
    @endif

    @error('*')
        <div class="alert alert-danger py-2" style="font-size:13px">{{ $message }}</div>
    @enderror

    <div class="row">
        {{-- ---------------- theme catalogue + pricing ---------------- --}}
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header py-2" style="font-size:13px;font-weight:600;color:#0b3c66">
                    Theme catalogue
                    <span style="font-weight:400;color:#61748a">({{ $themes->count() }} designs, price per facility)</span>
                </div>
                <div class="card-body p-2">
                    <form wire:submit="savePricing">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0" style="font-size:12.5px">
                                <thead>
                                    <tr style="color:#61748a">
                                        <th>Theme</th>
                                        <th style="width:96px">Palette</th>
                                        <th style="width:120px">Price</th>
                                        <th style="width:92px">Currency</th>
                                        <th style="width:74px">Sale</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($themes as $theme)
                                        <tr wire:key="theme-{{ $theme->id }}">
                                            <td>
                                                <div style="font-weight:600;color:#0b3c66">{{ $theme->name }}</div>
                                                <div style="color:#61748a;font-size:11.5px">{{ $theme->description }}</div>
                                            </td>
                                            <td>
                                                <span style="display:inline-block;width:22px;height:22px;border-radius:6px;background:{{ $theme->primary_color }}"></span>
                                                <span style="display:inline-block;width:22px;height:22px;border-radius:6px;background:{{ $theme->accent_color }}"></span>
                                                <span style="display:inline-block;width:22px;height:22px;border-radius:6px;background:{{ $theme->dark_color }}"></span>
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" min="0"
                                                    class="form-control form-control-sm"
                                                    wire:model="prices.{{ $theme->key }}">
                                            </td>
                                            <td>
                                                <input type="text" maxlength="8"
                                                    class="form-control form-control-sm"
                                                    wire:model="currencies.{{ $theme->key }}">
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm {{ $theme->is_active ? 'btn-outline-success' : 'btn-outline-secondary' }}"
                                                    wire:click="toggleTheme({{ $theme->id }})"
                                                    title="{{ $theme->is_active ? 'Hide from sale' : 'Offer for sale' }}">
                                                    {{ $theme->is_active ? 'On sale' : 'Hidden' }}
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="text-right mt-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-save"></i> Save pricing
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- ---------------- unlock / payment ---------------- --}}
            <div class="card">
                <div class="card-header py-2" style="font-size:13px;font-weight:600;color:#0b3c66">
                    Unlock a theme
                </div>
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <label class="mb-1" style="font-size:12px;color:#61748a">Facility</label>
                            <select class="form-control form-control-sm" wire:model="tenantId">
                                <option value="">Select a facility…</option>
                                @foreach ($tenants as $tenant)
                                    <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="mb-1" style="font-size:12px;color:#61748a">Theme</label>
                            <select class="form-control form-control-sm" wire:model="themeKey">
                                <option value="">Select a theme…</option>
                                @foreach ($themes as $theme)
                                    <option value="{{ $theme->key }}">{{ $theme->name }} — {{ $theme->priceLabel() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="mb-1" style="font-size:12px;color:#61748a">Amount</label>
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                wire:model="amount">
                            @error('amount') <span class="text-danger" style="font-size:11px">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="mb-1" style="font-size:12px;color:#61748a">Currency</label>
                            <input type="text" maxlength="8" class="form-control form-control-sm" wire:model="currency">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="mb-1" style="font-size:12px;color:#61748a">Payment mode</label>
                            <select class="form-control form-control-sm" wire:model="method">
                                <option value="manual">Manual</option>
                                <option value="cash">Cash</option>
                                <option value="bank">Bank transfer</option>
                                <option value="card">Card</option>
                                <option value="online">Online</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="mb-1" style="font-size:12px;color:#61748a">Reference</label>
                            <input type="text" maxlength="120" class="form-control form-control-sm"
                                wire:model="reference" placeholder="Receipt / transaction no.">
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="mb-1" style="font-size:12px;color:#61748a">Notes</label>
                            <input type="text" maxlength="1000" class="form-control form-control-sm"
                                wire:model="notes" placeholder="Optional">
                        </div>
                    </div>

                    <div class="d-flex align-items-center" style="gap:8px">
                        <button type="button" class="btn btn-primary btn-sm" wire:click="unlock">
                            <i class="fas fa-lock-open"></i> Record payment &amp; activate
                        </button>
                        @if ($selected && $selected->theme_key)
                            <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="clearTheme">
                                <i class="fas fa-undo"></i> Back to default design
                            </button>
                        @endif
                    </div>

                    @error('tenantId') <div class="text-danger mt-1" style="font-size:11.5px">{{ $message }}</div> @enderror
                    @error('themeKey') <div class="text-danger mt-1" style="font-size:11.5px">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        {{-- ---------------- facilities + payment history ---------------- --}}
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header py-2" style="font-size:13px;font-weight:600;color:#0b3c66">
                    Facilities
                </div>
                <div class="card-body p-2">
                    <input type="text" class="form-control form-control-sm mb-2" placeholder="Search facility, slug or domain"
                        wire:model.live.debounce.300ms="tenantSearch">
                    <div style="max-height:280px;overflow:auto">
                        <table class="table table-sm table-hover mb-0" style="font-size:12.5px">
                            <tbody>
                                @forelse ($tenants as $tenant)
                                    <tr wire:key="t-{{ $tenant->id }}" class="{{ $tenantId === $tenant->id ? 'table-active' : '' }}"
                                        style="cursor:pointer" wire:click="selectTenant({{ $tenant->id }})">
                                        <td>
                                            <div style="font-weight:600">{{ $tenant->name }}</div>
                                            <div style="font-size:11px;color:#61748a">
                                                {{ $tenant->domain ?: $tenant->subdomain ?: $tenant->slug }}
                                            </div>
                                        </td>
                                        <td class="text-right" style="white-space:nowrap">
                                            @if ($tenant->theme_key)
                                                <span class="badge" style="background:#0f7fd4">
                                                    {{ \App\Models\Theme::where('key', $tenant->theme_key)->value('name') ?? $tenant->theme_key }}
                                                </span>
                                            @else
                                                <span class="badge badge-light" style="color:#61748a">Default</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-center text-muted" style="font-size:12.5px">No facility found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header py-2" style="font-size:13px;font-weight:600;color:#0b3c66">
                    Payment history
                    @if ($selected)
                        <span style="font-weight:400;color:#61748a">— {{ $selected->name }}</span>
                    @endif
                </div>
                <div class="card-body p-2">
                    <div style="max-height:320px;overflow:auto">
                        <table class="table table-sm mb-0" style="font-size:12px">
                            <tbody>
                                @forelse ($payments as $payment)
                                    <tr wire:key="p-{{ $payment->id }}">
                                        <td>
                                            <div style="font-weight:600">{{ $payment->theme?->name ?? $payment->theme_key }}</div>
                                            <div style="color:#61748a">
                                                {{ $payment->methodLabel() }}
                                                @if ($payment->reference) · {{ $payment->reference }} @endif
                                            </div>
                                            <div style="color:#61748a;font-size:11px">
                                                {{ $payment->paid_at?->format('d M Y') ?? '—' }}
                                            </div>
                                        </td>
                                        <td class="text-right" style="white-space:nowrap">
                                            <div style="font-weight:600">{{ number_format((float) $payment->amount, 0) }} {{ $payment->currency }}</div>
                                            @if ($payment->revoked_at)
                                                <span class="badge badge-secondary">Revoked</span>
                                            @else
                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                    wire:click="revoke({{ $payment->id }})">Revoke</button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-center text-muted" style="font-size:12px">
                                            {{ $selected ? 'No payment recorded yet.' : 'Select a facility to see its payments.' }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
