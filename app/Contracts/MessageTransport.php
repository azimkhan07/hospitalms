<?php

namespace App\Contracts;

/**
 * Outbound message transport. A transport knows how to hand a message to a
 * carrier; it never decides WHAT to say or WHETHER it should send.
 */
interface MessageTransport
{
    /**
     * Deliver one message.
     *
     * @return array{status: string, provider: string, provider_ref: ?string, error: ?string}
     */
    public function send(string $channel, string $phone, string $body, array $options = []): array;
}