<?php

namespace App\Http\Livewire\SuperAdmin;

use App\Models\SystemError;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $stats = [
            'tenants' => Tenant::count(),
            'active' => Tenant::where('status', 'active')->count(),
            'hospitals' => Tenant::where('mode', 'hospital')->count(),
            'clinics' => Tenant::where('mode', 'clinic')->count(),
            'admins' => User::whereRelation('role', 'slug', 'admin')->count(),
            'unresolved' => SystemError::whereNull('resolved_at')->count(),
            'errors_24h' => SystemError::where('created_at', '>=', now()->subDay())->count(),
        ];

        return view('livewire.super-admin.dashboard', [
            'stats' => $stats,
            'recentTenants' => Tenant::withCount('users')->latest()->take(5)->get(),
            'recentErrors' => SystemError::with('tenant')->latest()->take(6)->get(),
        ]);
    }
}
