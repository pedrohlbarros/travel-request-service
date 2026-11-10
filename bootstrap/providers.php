<?php

declare(strict_types=1);

use Infrastructure\Identity\IdentityServiceProvider;
use Infrastructure\Shared\SharedServiceProvider;
use Interfaces\Console\Identity\IdentityConsoleServiceProvider;
use Interfaces\Http\Identity\IdentityHttpServiceProvider;
use Interfaces\Http\Shared\SharedHttpServiceProvider;

return [
    SharedServiceProvider::class,
    SharedHttpServiceProvider::class,
    IdentityServiceProvider::class,
    IdentityHttpServiceProvider::class,
    IdentityConsoleServiceProvider::class,
];
