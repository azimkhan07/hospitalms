<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Theme extends Model
{
    protected $fillable = [
        'key', 'name', 'description', 'price', 'currency',
        'primary_color', 'accent_color', 'dark_color',
        'is_premium', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_premium' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function payments()
    {
        return $this->hasMany(ThemePayment::class, 'theme_key', 'key');
    }

    public function priceLabel(): string
    {
        if ($this->isFree()) {
            return 'Free';
        }

        return number_format((float) $this->price, 0).' '.$this->currency;
    }

    public function isFree(): bool
    {
        return ! $this->is_premium || (float) $this->price <= 0;
    }

    /**
     * The theme a public request may render for this facility.
     *
     * Premium themes stay locked until the platform records a payment; only the
     * super admin unlocks them (clients never see the switch). A missing,
     * inactive or unpaid theme falls back to the default design.
     */
    public static function activeForTenant(?Tenant $tenant): ?self
    {
        if (! $tenant || blank($tenant->theme_key)) {
            return null;
        }

        $theme = self::query()
            ->where('key', $tenant->theme_key)
            ->where('is_active', true)
            ->first();

        if (! $theme) {
            return null;
        }

        if ($theme->isFree()) {
            return $theme;
        }

        $unlocked = ThemePayment::query()
            ->where('tenant_id', $tenant->id)
            ->where('theme_key', $theme->key)
            ->whereNull('revoked_at')
            ->exists();

        return $unlocked ? $theme : null;
    }

    public function stylesheet(): string
    {
        return 'themes/'.$this->key.'/theme.css';
    }
}
