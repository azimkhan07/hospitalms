<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The nursing shift hand-over note (PLAN.md Phase 6): what the outgoing
 * nurse needs the incoming role / ward to know before they take the keys.
 */
class Handover extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'from_user',
        'to_role',
        'ward',
        'notes',
    ];

    public function originator()
    {
        return $this->belongsTo(User::class, 'from_user');
    }
}
