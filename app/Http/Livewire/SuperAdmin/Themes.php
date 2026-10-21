<?php

namespace App\Http\Livewire\SuperAdmin;

use App\Models\Theme;
use App\Models\ThemePayment;
use App\Models\Tenant;
use Livewire\Component;

/**
 * Theme marketplace (platform side only).
 *
 * A facility's public website design is sold and unlocked here: the platform
 * records the payment, sets the facility's active theme and can revoke it
 * again. Facility admins never see this screen - only the super admin does.
 */
class Themes extends Component
{
    public string $tenantSearch = '';

    public ?int $tenantId = null;

    public string $themeKey = '';

    public string $amount = '';

    public string $currency = 'PKR';

    public string $method = 'manual';

    public string $reference = '';

    public string $notes = '';

    /** @var array<string, string> theme key => price */
    public array $prices = [];

    /** @var array<string, string> theme key => currency */
    public array $currencies = [];

    public function mount(): void
    {
        foreach (Theme::orderBy('sort_order')->get() as $theme) {
            $this->prices[$theme->key] = (string) $theme->price;
            $this->currencies[$theme->key] = $theme->currency;
        }
    }

    public function savePricing(): void
    {
        $this->validate([
            'prices.*' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'currencies.*' => ['required', 'string', 'max:8'],
        ]);

        foreach ($this->prices as $key => $price) {
            Theme::where('key', $key)->update([
                'price' => $price,
                'currency' => $this->currencies[$key] ?? 'PKR',
            ]);
        }

        session()->flash('sanotice', 'Theme pricing saved.');
    }

    public function toggleTheme(int $id): void
    {
        $theme = Theme::findOrFail($id);
        $theme->is_active = ! $theme->is_active;
        $theme->save();

        session()->flash('sanotice', "{$theme->name} is now ".($theme->is_active ? 'available' : 'hidden from sale').'.');
    }

    public function selectTenant(int $id): void
    {
        $tenant = Tenant::findOrFail($id);

        $this->tenantId = $tenant->id;
        $this->themeKey = $tenant->theme_key ?: '';
        $this->reference = '';
        $this->notes = '';
        $this->method = 'manual';

        $theme = $this->themeKey ? Theme::where('key', $this->themeKey)->first() : null;

        $this->amount = $theme ? (string) $theme->price : '';
        $this->currency = $theme ? $theme->currency : 'PKR';
    }

    public function updatedThemeKey(): void
    {
        $theme = Theme::where('key', $this->themeKey)->first();

        $this->amount = $theme ? (string) $theme->price : '';
        $this->currency = $theme ? $theme->currency : 'PKR';
    }

    /**
     * Record the payment and switch the facility onto the theme.
     */
    public function unlock(): void
    {
        $this->validate([
            'tenantId' => ['required', 'integer', 'exists:tenants,id'],
            'themeKey' => ['required', 'string', 'exists:themes,key'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'currency' => ['required', 'string', 'max:8'],
            'method' => ['required', 'in:manual,cash,bank,card,online'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $tenant = Tenant::findOrFail($this->tenantId);
        $theme = Theme::where('key', $this->themeKey)->firstOrFail();

        ThemePayment::create([
            'tenant_id' => $tenant->id,
            'theme_key' => $theme->key,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'method' => $this->method,
            'reference' => $this->reference ?: null,
            'notes' => $this->notes ?: null,
            'paid_at' => now(),
            'unlocked_by' => auth()->id(),
        ]);

        $tenant->forceFill(['theme_key' => $theme->key])->save();

        session()->flash('sanotice', "{$theme->name} unlocked for {$tenant->name}.");
    }

    /**
     * Revoke an unlock: the facility falls back to the default design.
     */
    public function revoke(int $paymentId): void
    {
        $payment = ThemePayment::findOrFail($paymentId);

        if ($payment->revoked_at) {
            return;
        }

        $payment->forceFill(['revoked_at' => now()])->save();

        $tenant = Tenant::find($payment->tenant_id);

        if ($tenant && $tenant->theme_key === $payment->theme_key) {
            $tenant->forceFill(['theme_key' => null])->save();
        }

        session()->flash('sanotice', 'Theme access revoked.');
    }

    /**
     * Drop the theme without touching the payment history.
     */
    public function clearTheme(): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);
        $tenant->forceFill(['theme_key' => null])->save();

        session()->flash('sanotice', "{$tenant->name} is back on the default design.");
    }

    public function render()
    {
        $themes = Theme::orderBy('sort_order')->get();

        $tenants = Tenant::query()
            ->when($this->tenantSearch !== '', function ($q) {
                $term = '%'.mb_strtolower($this->tenantSearch).'%';
                $q->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(slug) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(domain) LIKE ?', [$term]);
                });
            })
            ->orderBy('name')
            ->limit(100)
            ->get();

        $selected = $this->tenantId ? Tenant::find($this->tenantId) : null;

        $payments = $selected
            ? ThemePayment::with('theme')->where('tenant_id', $selected->id)->latest('id')->limit(25)->get()
            : collect();

        return view('livewire.super-admin.themes', [
            'themes' => $themes,
            'tenants' => $tenants,
            'selected' => $selected,
            'payments' => $payments,
        ]);
    }
}
