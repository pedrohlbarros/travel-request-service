<?php

declare(strict_types=1);

return [
    // CloudEvents "source" of every event this service publishes.
    'source' => env('MESSAGING_EVENT_SOURCE', '/travel-request-service'),

    'relay' => [
        'min_poll_ms' => (int) env('OUTBOX_RELAY_MIN_POLL_MS', 200),
        'max_poll_ms' => (int) env('OUTBOX_RELAY_MAX_POLL_MS', 2000),
        'batch_size' => (int) env('OUTBOX_RELAY_BATCH_SIZE', 100),
        'confirm_timeout_seconds' => (int) env('OUTBOX_RELAY_CONFIRM_TIMEOUT', 5),
    ],

    'tap' => [
        'exchange' => env('TRAVEL_REQUEST_EVENTS_EXCHANGE', 'travel_request.events'),
        'max_length' => 1000,
    ],
];
