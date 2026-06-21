<?php

return [
    'default' => env('MAIL_MAILER', 'array'),
    'mailers' => [
        'array' => [
            'transport' => 'array',
        ],
    ],
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'HFCCF')),
    ],
];
