<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\medicine;
use App\Models\Settings;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\DigestReady;
use Illuminate\Support\Carbon;

/**
 * Phase 10 - in-app digest for the management roles.
 *
 * Once a day the reports screen runs the numbers and pings every active
 * admin/moderator/accountant with a one-line digest (unpaid bills, low stock,
 * pending deliveries). The Settings row guards the run so a page refresh or a
 * second browser tab can never spam the bell. SMS/WhatsApp/email channels are
 * a later phase; this is the in-app part.
 */
class NotificationDigest
{
    public static function run(): void
    {
        $user = auth()?->user();

        if (! $user?->tenant_id) {
            return;
        }

        $tenantId = (int) $user->tenant_id;
        $key = 'reports_digest_last:'.$tenantId;

        $last = (string) Settings::where('key', $key)->value('value');

        if ($last === Carbon::today()->toDateString()) {
            return;
        }

        $bills = (int) \App\Models\bill::where('status', 'unpaid')->count();
        $low = (int) medicine::where(function ($q) {
            $q->where('stock', '<=', 0)
                ->orWhereColumn('stock', '<', 'reorder_level');
        })->count();
        $pending = DeliveryOrder::whereIn('status', [DeliveryOrder::PENDING, DeliveryOrder::ASSIGNED])->count();

        if ($bills + $low + $pending === 0) {
            return;
        }

        $message = sprintf(
            'Morning digest: %d unpaid bill%s, %d low-stock item%s, %d delivery%s pending.',
            $bills,
            $bills === 1 ? '' : 's',
            $low,
            $low === 1 ? '' : 's',
            $pending,
            $pending === 1 ? '' : 's'
        );

        $slugs = static::managementRoleSlugs($tenantId);

        $reachables = User::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->whereIn('slug', $slugs))
            ->get();

        foreach ($reachables as $recipient) {
            $recipient->notify(new DigestReady($message));
        }

        Settings::updateOrCreate(['key' => $key], ['value' => Carbon::today()->toDateString()]);
    }

    private static function managementRoleSlugs(int $tenantId): array
    {
        $required = Tenant::requiredRoleSlugs($tenantId);

        if (in_array('admin', $required, true)) {
            return ['admin', 'moderator', 'accountant'];
        }

        return array_values(array_intersect(
            ['moderator', 'accountant'],
            $required
        ));
    }
}