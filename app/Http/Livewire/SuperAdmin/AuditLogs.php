<?php

namespace App\Http\Livewire\SuperAdmin;

use App\Models\AuditLog;
use App\Models\Tenant;
use Livewire\Component;
use Livewire\WithPagination;

class AuditLogs extends Component
{
    use WithPagination;

    public string $search = '';

    public string $action = '';

    public string $tenantFilter = '';

    public array $counts = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedAction(): void
    {
        $this->resetPage();
    }

    public function updatedTenantFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->action = '';
        $this->tenantFilter = '';
        $this->resetPage();
    }

    public function render()
    {
        $logs = AuditLog::query()
            ->with(['tenant', 'user'])
            ->when($this->tenantFilter !== '', fn ($q) => $q->where('tenant_id', $this->tenantFilter))
            ->when($this->action !== '', fn ($q) => $q->where('action', $this->action))
            ->when(trim($this->search) !== '', function ($q) {
                $term = '%'.trim($this->search).'%';
                $q->where(fn ($qq) => $qq
                    ->where('summary', 'like', $term)
                    ->orWhere('auditable_type', 'like', $term)
                    ->orWhere('ip', 'like', $term));
            })
            ->latest('created_at')
            ->latest('id')
            ->paginate(15);

        $this->counts = [
            'total' => AuditLog::count(),
            'created' => AuditLog::where('action', 'created')->count(),
            'updated' => AuditLog::where('action', 'updated')->count(),
            'deleted' => AuditLog::where('action', 'deleted')->count(),
            'tenants' => Tenant::count(),
        ];

        return view('livewire.super-admin.audit-logs', [
            'logs' => $logs,
            'tenants' => Tenant::orderBy('name')->get(['id', 'name']),
            'actions' => ['created', 'updated', 'deleted'],
        ]);
    }
}