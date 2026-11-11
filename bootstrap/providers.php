<?php

declare(strict_types=1);

use Infrastructure\Identity\IdentityServiceProvider;
use Infrastructure\Notification\NotificationServiceProvider;
use Infrastructure\Shared\SharedServiceProvider;
use Infrastructure\TravelRequest\TravelRequestServiceProvider;
use Interfaces\Console\Identity\IdentityConsoleServiceProvider;
use Interfaces\Http\Identity\IdentityHttpServiceProvider;
use Interfaces\Http\Notification\NotificationHttpServiceProvider;
use Interfaces\Http\Shared\SharedHttpServiceProvider;
use Interfaces\Http\TravelRequest\TravelRequestHttpServiceProvider;

return [
    SharedServiceProvider::class,
    SharedHttpServiceProvider::class,
    IdentityServiceProvider::class,
    IdentityHttpServiceProvider::class,
    IdentityConsoleServiceProvider::class,
    TravelRequestServiceProvider::class,
    TravelRequestHttpServiceProvider::class,
    NotificationServiceProvider::class,
    NotificationHttpServiceProvider::class,
];
