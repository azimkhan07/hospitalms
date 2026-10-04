<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantRoleRequirement extends Model
{
    protected $table = 'tenant_role_requirements';

    protected $fillable = ['tenant_id', 'role_id'];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}