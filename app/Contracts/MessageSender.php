<?php

namespace App\Contracts;

use App\Models\MessageLog;

/**
 * High-level sender the rest of the app talks to. The container binds it to a
 * configured transport, so callers just say sms()/whatsapp() and never care
 * which carrier is behind the scenes.
 */
interface MessageSender
{
    public function sms(string $phone, string $body, array $options = []): MessageLog;

    public function whatsapp(string $phone, string $body, array $options = []): MessageLog;
}