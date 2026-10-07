<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);

        $logs = AuditLog::query()
            ->with(['tenant:id,name', 'user:id,name'])
            ->when($request->filled('tenant_id'), fn ($q) => $q->where('tenant_id', $request->integer('tenant_id')))
            ->when($request->filled('action') && in_array($request->string('action'), ['created', 'updated', 'deleted'], true), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where(fn ($qq) => $qq->where('summary', 'like', $term)->orWhere('auditable_type', 'like', $term));
            })
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $logs->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'tenant_id' => $log->tenant_id,
                'tenant' => $log->tenant?->name,
                'action' => $log->action,
                'resource' => class_basename($log->auditable_type),
                'resource_id' => $log->auditable_id,
                'summary' => $log->summary,
                'actor' => $log->user?->name ?? 'system',
                'actor_id' => $log->user_id,
                'old' => $log->old,
                'new' => $log->new,
                'ip' => $log->ip,
                'at' => $log->created_at->toDateTimeString(),
            ]),
        ]);
    }
}