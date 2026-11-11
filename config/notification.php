<?php

declare(strict_types=1);

return [
    'locale' => env('NOTIFICATION_LOCALE', 'pt_BR'),

    'consumer' => [
        'exchange' => env('TRAVEL_REQUEST_EVENTS_EXCHANGE', 'travel_request.events'),
        'queue' => env('NOTIFICATION_QUEUE', 'notification.travel_request'),
        'binding_key' => 'travel_request.#',
        'prefetch' => (int) env('NOTIFICATION_CONSUMER_PREFETCH', 10),
        'retry_delays_ms' => env('NOTIFICATION_RETRY_DELAYS_MS', '10000,60000,300000'),
        'confirm_timeout_seconds' => (int) env('NOTIFICATION_CONFIRM_TIMEOUT', 5),
        'wait_ms' => (int) env('NOTIFICATION_CONSUMER_WAIT_MS', 1000),
    ],

    'playground' => [
        'key' => env('REVERB_APP_KEY'),
        'host' => env('REVERB_PUBLIC_HOST', 'localhost'),
        'port' => (int) env('FORWARD_REVERB_PORT', 8080),
        'scheme' => env('REVERB_PUBLIC_SCHEME', 'http'),
    ],
];
