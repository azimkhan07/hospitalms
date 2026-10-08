<?php

namespace App\Services\Messaging;

use App\Contracts\MessageTransport;
use App\Models\MessageLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

/**
 * "http" transport posts the message to a configurable gateway URL.
 * It is the seam where a real SMS / WhatsApp provider drops in: point
 * config('messaging.sms.url') / config('messaging.whatsapp.url') at it.
 */
class HttpMessageTransport implements MessageTransport
{
    public function send(string $channel, string $phone, string $body, array $options = []): array
    {
        $config = config("messaging.{$channel}", []);
        $url = $config['url'] ?? null;

        if (! $url) {
            return $this->fail($channel, $phone, 'No gateway URL configured.');
        }

        try {
            $response = Http::timeout(10)->asJson()->post($url, array_merge([
                'phone' => $phone,
                'message' => $body,
                'from' => $config['from'] ?? null,
            ], $options));

            $ok = $response->successful();

            MessageLog::create([
                'tenant_id' => Auth::user()?->tenant_id,
                'channel' => $channel,
                'phone' => $phone,
                'body' => $body,
                'status' => $ok ? 'sent' : 'failed',
                'provider' => 'http',
                'provider_ref' => $ok ? (string) $response->json('id') : null,
                'error' => $ok ? null : $response->body(),
            ]);

            return [
                'status' => $ok ? 'sent' : 'failed',
                'provider' => 'http',
                'provider_ref' => $ok ? (string) $response->json('id') : null,
                'error' => $ok ? null : $response->body(),
            ];
        } catch (\Throwable $e) {
            return $this->fail($channel, $phone, $e->getMessage());
        }
    }

    private function fail(string $channel, string $phone, string $error): array
    {
        MessageLog::create([
            'tenant_id' => Auth::user()?->tenant_id,
            'channel' => $channel,
            'phone' => $phone,
            'body' => 'delivery failed before send',
            'status' => 'failed',
            'provider' => 'http',
            'error' => $error,
        ]);

        return ['status' => 'failed', 'provider' => 'http', 'provider_ref' => null, 'error' => $error];
    }
}