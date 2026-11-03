<?php

declare(strict_types=1);

return [
    'clinics' => [
        'A' => [
            'admin_password' => env('HOMOLOG_A_ADMIN_PASSWORD'),
            'reception_password' => env('HOMOLOG_A_RECEPTION_PASSWORD'),
            'totp_secret' => env('HOMOLOG_A_TOTP_SECRET'),
        ],
        'B' => [
            'admin_password' => env('HOMOLOG_B_ADMIN_PASSWORD'),
            'reception_password' => env('HOMOLOG_B_RECEPTION_PASSWORD'),
            'totp_secret' => env('HOMOLOG_B_TOTP_SECRET'),
        ],
    ],
];
