<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One outbound SMS / WhatsApp message. Kept for audit -- who we messaged,
 * with what, through which provider, and whether it went out.
 */
class MessageLog extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'channel', 'phone', 'body', 'status', 'provider', 'provider_ref', 'error',
    ];
}