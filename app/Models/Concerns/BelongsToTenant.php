<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Scopes a model to the signed-in facility.
 *
 * Applied as a global scope so a forgotten where() cannot leak one facility's
 * wards, beds or departments into another's. Only applies while a tenant is
 * actually signed in, so the platform panel and console queries still see
 * everything.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantId = static::currentTenantId();

            if ($tenantId) {
                $builder->where(
                    $builder->getModel()->getTable().'.tenant_id',
                    $tenantId
                );
            }
        });
    }

    public static function currentTenantId(): ?int
    {
        if (app()->runningInConsole() && ! request()->hasSession()) {
            return null;
        }

        $user = auth()->user();

        return $user?->tenant_id ? (int) $user->tenant_id : null;
    }

    /**
     * Escape the scope for platform-wide work (the Super Admin views, exports).
     */
    public static function acrossTenants(): Builder
    {
        return static::query()->withoutGlobalScope('tenant');
    }

    public function scopeForTenant(Builder $query, ?int $tenantId): Builder
    {
        return $query->where($this->getTable().'.tenant_id', $tenantId);
    }
}