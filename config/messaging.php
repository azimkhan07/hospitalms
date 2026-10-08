<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Outbound messaging
    |--------------------------------------------------------------------------
    | driver "log" writes every send to message_logs (dev/test). Switch to
    | "http" and set the gateway URLs to push real SMS/WhatsApp messages.
    */
    'driver' => env('MESSAGING_DRIVER', 'log'),

    'enabled' => (bool) env('MESSAGING_ENABLED', true),

    'sms' => [
        'from' => env('SMS_FROM', 'HospitalMS'),
        'url' => env('SMS_URL'),
        'sid' => env('SMS_SID'),
        'token' => env('SMS_TOKEN'),
        'template' => env('SMS_TEMPLATE', '{message}'),
    ],

    'whatsapp' => [
        'from' => env('WHATSAPP_FROM', 'HospitalMS'),
        'url' => env('WHATSAPP_URL'),
        'token' => env('WHATSAPP_TOKEN'),
        'wa_me' => (bool) env('WHATSAPP_WA_ME', true),
    ],
];