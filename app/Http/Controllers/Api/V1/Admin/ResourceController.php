<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\appointment;
use App\Models\medicine;
use App\Models\patient;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResourceController extends Controller
{
    public function patients(Request $request): JsonResponse
    {
        return $this->paginated($request, patient::query());
    }

    public function appointments(Request $request): JsonResponse
    {
        return $this->paginated($request, appointment::query()->latest());
    }

    public function medicines(Request $request): JsonResponse
    {
        return $this->paginated(
            $request,
            medicine::whereNull('deleted_at')->orderBy('name')
        );
    }

    public function staff(Request $request): JsonResponse
    {
        return $this->paginated($request, User::with('role')->orderBy('name'));
    }

    protected function paginated(Request $request, Builder $query): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);

        /** @var LengthAwarePaginator $page */
        $page = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'success' => true,
            'data' => $page,
        ]);
    }
}
