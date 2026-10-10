<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Icd10Code;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * API mirror of the ICD-10 coding directory: look codes up by text, add a
 * facility code and remove one. The global baseline (tenant_id NULL) is shared
 * with every facility, so only the baseline plus the caller's own rows are ever
 * visible or removable.
 */
class Icd10CodesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(hms_can('staff', $request->user()), 403);

        $codes = Icd10Code::query()
            ->forTenant($this->tenantId($request))
            ->search((string) $request->query('search', ''))
            ->when(! $request->boolean('archived'), fn ($q) => $q->active())
            ->orderBy('code')
            ->get()
            ->map(fn (Icd10Code $row): array => [
                'id' => $row->id,
                'code' => $row->code,
                'description' => $row->description,
                'chapter' => $row->chapter,
            ]);

        return response()->json([
            'success' => true,
            'data' => $codes,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(hms_can('staff', $request->user()), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', Rule::unique('icd10_codes', 'code')],
            'description' => ['required', 'string', 'max:300'],
            'chapter' => ['nullable', 'string', 'max:120'],
        ]);

        $row = Icd10Code::create([
            'tenant_id' => $this->tenantId($request),
            'code' => strtoupper(trim($data['code'])),
            'description' => $data['description'],
            'chapter' => $data['chapter'] ?? null,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'ICD-10 code created.',
            'data' => [
                'id' => $row->id,
                'code' => $row->code,
                'description' => $row->description,
                'chapter' => $row->chapter,
            ],
        ], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        abort_unless(hms_can('staff', $request->user()), 403);

        // A foreign facility's row is outside the forTenant() window, so it
        // reads as missing rather than being silently deletable.
        $row = Icd10Code::query()
            ->forTenant($this->tenantId($request))
            ->whereKey($id)
            ->first();

        if (! $row) {
            return response()->json([
                'success' => false,
                'message' => 'ICD-10 code not found.',
            ], 404);
        }

        $row->delete();

        return response()->json([
            'success' => true,
            'message' => 'ICD-10 code removed.',
            'data' => ['id' => (int) $id],
        ]);
    }

    private function tenantId(Request $request): ?int
    {
        $tenantId = $request->user()?->tenant_id;

        return $tenantId ? (int) $tenantId : null;
    }
}
