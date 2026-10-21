<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Theme;
use App\Models\ThemePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThemeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Theme::orderBy('sort_order')->get(),
        ]);
    }

    public function payments(Request $request): JsonResponse
    {
        $tenant = Tenant::findOrFail($request->integer('tenant_id'));

        return response()->json([
            'success' => true,
            'data' => ThemePayment::with('theme')
                ->where('tenant_id', $tenant->id)
                ->latest('id')
                ->limit(50)
                ->get(),
        ]);
    }

    /**
     * Record a theme payment and switch the facility onto that design.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'theme_key' => ['required', 'string', 'exists:themes,key'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'currency' => ['nullable', 'string', 'max:8'],
            'method' => ['nullable', 'in:manual,cash,bank,card,online'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $tenant = Tenant::findOrFail($data['tenant_id']);
        $theme = Theme::where('key', $data['theme_key'])->firstOrFail();

        $payment = ThemePayment::create([
            'tenant_id' => $tenant->id,
            'theme_key' => $theme->key,
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? $theme->currency,
            'method' => $data['method'] ?? 'manual',
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'paid_at' => now(),
            'unlocked_by' => $request->user()->id,
        ]);

        $tenant->forceFill(['theme_key' => $theme->key])->save();

        return response()->json([
            'success' => true,
            'message' => 'Theme unlocked and activated.',
            'data' => $payment,
        ], 201);
    }

    /**
     * Revoke an unlock: the facility falls back to the default design.
     */
    public function destroy(int $payment): JsonResponse
    {
        $record = ThemePayment::findOrFail($payment);

        if (! $record->revoked_at) {
            $record->forceFill(['revoked_at' => now()])->save();

            $tenant = Tenant::find($record->tenant_id);

            if ($tenant && $tenant->theme_key === $record->theme_key) {
                $tenant->forceFill(['theme_key' => null])->save();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Theme access revoked.',
        ]);
    }
}
