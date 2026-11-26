<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'whatsapp' => [
        'enabled' => env('WHATSAPP_ENABLED', false),
        'provider' => env('WHATSAPP_PROVIDER', 'fonnte'),
        'api_url' => env('WHATSAPP_API_URL', 'https://api.fonnte.com/send'),
        'token' => env('WHATSAPP_TOKEN', env('FONNTE_TOKEN')),
        'timeout' => (int) env('WHATSAPP_TIMEOUT', 15),
        'test_mode' => env('WHATSAPP_TEST_MODE', false),
        'test_number' => env('WHATSAPP_TEST_NUMBER'),
        'iuran_reminder' => [
            'enabled' => env('WHATSAPP_IURAN_REMINDER_ENABLED', true),
            'time' => env('WHATSAPP_IURAN_REMINDER_TIME', '08:00'),
            'timezone' => env('WHATSAPP_IURAN_REMINDER_TIMEZONE', 'Asia/Jakarta'),
        ],
        'lomba_reminder' => [
            'enabled' => env('WHATSAPP_LOMBA_REMINDER_ENABLED', true),
            'time_h1' => env('WHATSAPP_LOMBA_REMINDER_TIME_H1', '08:00'),
            'time_h0' => env('WHATSAPP_LOMBA_REMINDER_TIME_H0', '07:00'),
            'timezone' => env('WHATSAPP_LOMBA_REMINDER_TIMEZONE', 'Asia/Jakarta'),
        ],
        'match_invitation' => [
            'enabled' => env('WHATSAPP_MATCH_INVITATION_ENABLED', true),
            'rw_number' => env('WHATSAPP_MATCH_INVITATION_RW_NUMBER', '01'),
            'lead_minutes' => (int) env('WHATSAPP_MATCH_INVITATION_LEAD_MINUTES', 30),
            'window_minutes' => (int) env('WHATSAPP_MATCH_INVITATION_WINDOW_MINUTES', 5),
            'max_schedule_updates' => (int) env('WHATSAPP_MATCH_INVITATION_MAX_UPDATES', 1),
            'timezone' => env('WHATSAPP_MATCH_INVITATION_TIMEZONE', 'Asia/Jakarta'),
        ],
    ],

    'scheduler' => [
        'token' => env('SCHEDULER_CRON_TOKEN'),
        'lock_ttl' => (int) env('SCHEDULER_LOCK_TTL', 240),
    ],

];
