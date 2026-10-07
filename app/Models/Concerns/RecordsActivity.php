<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Writes a lightweight "who did what to which record" trail.
 *
 * Hooked into a model's created/updated/deleted events; the audit write is
 * best-effort and must never break the business write itself, so everything
 * is wrapped in a try/catch.
 */
trait RecordsActivity
{
    public static function bootRecordsActivity(): void
    {
        foreach (['created', 'updated', 'deleted'] as $event) {
            static::registerModelEvent($event, function (Model $model) use ($event) {
                static::recordActivity($model, $event);
            });
        }
    }

    public static function auditResourceLabel(): string
    {
        $base = class_basename(static::class);

        return ucfirst(preg_replace('/(?<=\\w)(?=[A-Z])/', ' ', $base) ?? $base);
    }

    protected static function recordActivity(Model $model, string $action): void
    {
        try {
            $user = auth()->user();

            AuditLog::create([
                'tenant_id' => $model->getAttribute('tenant_id')
                    ?? $user?->tenant_id
                    ?? (method_exists(static::class, 'currentTenantId') ? static::currentTenantId() : null),
                'user_id' => $user?->id,
                'action' => $action,
                'auditable_type' => get_class($model),
                'auditable_id' => $model->getKey(),
                'summary' => static::auditSummary($model, $action),
                'old' => $action === 'created' ? null : ($model->getOriginal() ?: null),
                'new' => $action === 'deleted' ? null : ($model->getAttributes() ?: null),
                'ip' => request()->ip(),
            ]);
        } catch (Throwable) {
            // Never let a trail write take down the operation it is recording.
        }
    }

    protected static function auditSummary(Model $model, string $action): string
    {
        $label = method_exists($model, 'auditName')
            ? $model->auditName()
            : (string) ($model->getAttribute('name') ?? $model->getAttribute('invoice_no') ?? '#'.$model->getKey());

        return $action.' '.static::auditResourceLabel().' '.$label;
    }
}