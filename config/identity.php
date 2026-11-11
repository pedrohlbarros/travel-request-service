<?php

declare(strict_types=1);

return [
    'jwt' => [
        'issuer' => env('JWT_ISSUER', 'http://localhost:8000'),
        'audience' => env('JWT_AUDIENCE', 'travel-request-service'),
        'private_key_path' => env('JWT_PRIVATE_KEY_PATH', storage_path('keys/jwt-private.pem')),
        'public_key_path' => env('JWT_PUBLIC_KEY_PATH', storage_path('keys/jwt-public.pem')),
        'access_token_ttl' => (int) env('JWT_ACCESS_TOKEN_TTL', 900),
    ],

    'refresh_token_ttl' => (int) env('REFRESH_TOKEN_TTL', 604800),

    'role_cache_ttl' => (int) env('IDENTITY_ROLE_CACHE_TTL', 300),

    'rate_limit' => [
        'attempts_per_minute' => (int) env('AUTH_RATE_LIMIT_PER_MINUTE', 5),
    ],
];
