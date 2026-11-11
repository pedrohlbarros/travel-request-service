<?php

declare(strict_types=1);

return [
    'cache' => [
        'detail_ttl' => (int) env('TRAVEL_REQUEST_CACHE_DETAIL_TTL', 300),
        'list_ttl' => (int) env('TRAVEL_REQUEST_CACHE_LIST_TTL', 30),
    ],
];
