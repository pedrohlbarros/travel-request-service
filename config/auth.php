<?php

declare(strict_types=1);

return [
    'defaults' => [
        'guard' => 'api',
        'passwords' => null,
    ],

    'guards' => [
        'api' => [
            'driver' => 'jwt',
        ],
    ],

    'providers' => [],
];
