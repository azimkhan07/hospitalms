<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SystemError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ErrorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);

        $errors = SystemError::query()
            ->when(
                $request->filled('resolved'),
                fn ($q) => $request->boolean('resolved')
                    ? $q->whereNotNull('resolved_at')
                    : $q->whereNull('resolved_at')
            )
            ->when(
                $request->filled('level'),
                fn ($q) => $q->where('level', $request->string('level'))
            )
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $errors,
        ]);
    }
}
