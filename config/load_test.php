<?php

declare(strict_types=1);

return [
    'users' => (int) env('LOAD_TEST_USERS', 50),
    'admins' => (int) env('LOAD_TEST_ADMINS', 5),
    'travel_requests' => (int) env('LOAD_TEST_TRAVEL_REQUESTS', 10000),
];
