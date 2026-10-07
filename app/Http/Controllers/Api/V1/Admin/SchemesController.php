<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Scheme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchemesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! hms_can('schemes')) {
            abort(403);
        }

        $schemes = Scheme::withCount('patients')
            ->when($request->string('active')->toString() !== '', fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('name')
            ->limit(100)
            ->get()
            ->map(fn (Scheme $s): array => [
                'id' => $s->id,
                'name' => $s->name,
                'code' => $s->code,
                'provider' => $s->provider,
                'coverage_type' => $s->coverage_type,
                'coverage_value' => $s->coverage_value !== null ? (float) $s->coverage_value : null,
                'patients_count' => $s->patients_count,
                'is_active' => $s->is_active,
            ]);

        return response()->json(['success' => true, 'data' => $schemes]);
    }
}