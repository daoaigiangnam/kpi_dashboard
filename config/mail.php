<?php

return [
    // Production mail settings are loaded from the encrypted system_settings table.
    'default' => 'log',

    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'scheme' => null,
            'url' => null,
            'host' => '127.0.0.1',
            'port' => 2525,
            'username' => null,
            'password' => null,
            'timeout' => null,
            'local_domain' => parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST),
        ],
        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],
        'array' => [
            'transport' => 'array',
        ],
    ],

    'from' => [
        'address' => 'hello@example.com',
        'name' => env('APP_NAME', 'KPI Dashboard'),
    ],
];
