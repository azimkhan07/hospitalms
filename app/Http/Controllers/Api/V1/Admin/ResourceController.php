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
        $this->denyUnless('patients');

        return $this->paginated($request, patient::query());
    }

    public function appointments(Request $request): JsonResponse
    {
        $this->denyUnless('appointments');

        $query = appointment::query()->latest();

        // A doctor on the API sees only their own board, same as the web.
        if ($request->user()?->hasRole('doctor')) {
            $query->ownedBy((int) $request->user()->id);
        }

        return $this->paginated($request, $query);
    }

    public function medicines(Request $request): JsonResponse
    {
        $this->denyUnless('medicines');

        return $this->paginated(
            $request,
            medicine::whereNull('deleted_at')->orderBy('name')
        );
    }

    public function staff(Request $request): JsonResponse
    {
        $this->denyUnless('staff');

        // Staff lists are tenant data: never leak another facility's users
        // (and never the platform accounts) to a tenant login.
        $users = User::with('role')
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name');

        return $this->paginated($request, $users);
    }

    /** Same module gate the web screen applies, checked per endpoint. */
    protected function denyUnless(string $module): void
    {
        if (! hms_can($module)) {
            abort(403);
        }
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
