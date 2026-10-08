<?php

namespace App\Services\Messaging;

use App\Contracts\MessageTransport;
use App\Models\MessageLog;
use Illuminate\Support\Facades\Auth;

/**
 * The "log" transport is the default: it records the message in message_logs
 * and returns. Dev/test installs never touch a real carrier, and the audit
 * row is exactly what production inspection wants anyway.
 */
class LogMessageTransport implements MessageTransport
{
    public function send(string $channel, string $phone, string $body, array $options = []): array
    {
        MessageLog::create([
            'tenant_id' => Auth::user()?->tenant_id,
            'channel' => $channel,
            'phone' => $phone,
            'body' => $body,
            'status' => 'sent',
            'provider' => 'log',
        ]);

        return ['status' => 'sent', 'provider' => 'log', 'provider_ref' => null, 'error' => null];
    }
}