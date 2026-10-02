<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function __construct(protected TenantService $tenants)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);

        $tenants = Tenant::withCount('users')
            ->when(
                $request->filled('search'),
                fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%')
            )
            ->when(
                $request->filled('mode'),
                fn ($q) => $q->where('mode', $request->string('mode'))
            )
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $tenants,
        ]);
    }

    public function show(Tenant $tenant): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $tenant->loadCount('users'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'mode' => ['required', 'in:clinic,hospital'],
            'slug' => ['nullable', 'string', 'max:150'],
            'domain' => ['nullable', 'string', 'max:150'],
            'subdomain' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'working_hours' => ['nullable', 'string', 'max:150'],
            'admin_name' => ['nullable', 'string', 'max:150'],
            'admin_email' => ['nullable', 'email', 'max:150', 'required_with:admin_name'],
            'admin_password' => ['nullable', 'string', 'min:6'],
        ]);

        $admin = null;

        if (! empty($data['admin_email'])) {
            $admin = [
                'name' => $data['admin_name'] ?? 'Administrator',
                'email' => $data['admin_email'],
                'password' => $data['admin_password'] ?? '123456',
            ];
        }

        $data['created_by'] = $request->user()->id;

        $tenant = $this->tenants->create($data, $admin);

        return response()->json([
            'success' => true,
            'message' => 'Tenant created successfully.',
            'data' => $tenant->loadCount('users'),
        ], 201);
    }

    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'mode' => ['sometimes', 'in:clinic,hospital'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:150'],
            'domain' => ['sometimes', 'nullable', 'string', 'max:150'],
            'subdomain' => ['sometimes', 'nullable', 'string', 'max:150'],
            'status' => ['sometimes', 'in:active,inactive,suspended'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'working_hours' => ['sometimes', 'nullable', 'string', 'max:150'],
        ]);

        $data['name'] = $data['name'] ?? $tenant->name;
        $data['slug'] = $data['slug'] ?? $tenant->slug;

        $tenant = $this->tenants->update($tenant, $data);

        return response()->json([
            'success' => true,
            'message' => 'Tenant updated successfully.',
            'data' => $tenant,
        ]);
    }

    public function destroy(Tenant $tenant): JsonResponse
    {
        $tenant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tenant deleted successfully.',
        ]);
    }
}
