<?php

namespace App\Services\Messaging;

use App\Contracts\MessageSender;
use App\Models\MessageLog;

/**
 * High-level sender the rest of the app uses. It applies the shared rules
 * (enabled flag, non-empty phone) and delegates delivery to whatever transport
 * the container bound.
 */
class MessagingService implements MessageSender
{
    public function __construct(protected mixed $transport) {}

    public function sms(string $phone, string $body, array $options = []): MessageLog
    {
        return $this->send('sms', $phone, $body, $options);
    }

    public function whatsapp(string $phone, string $body, array $options = []): MessageLog
    {
        return $this->send('whatsapp', $phone, $body, $options);
    }

    protected function send(string $channel, string $phone, string $body, array $options): MessageLog
    {
        $phone = trim((string) $phone);

        if (! config('messaging.enabled', true) || $phone === '') {
            return new MessageLog(['channel' => $channel, 'phone' => $phone, 'body' => $body]);
        }

        $result = $this->transport->send($channel, $phone, $body, $options);

        return MessageLog::query()
            ->where('channel', $channel)
            ->where('phone', $phone)
            ->latest()
            ->first() ?? new MessageLog($result);
    }
}