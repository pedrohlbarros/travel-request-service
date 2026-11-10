<?php

declare(strict_types=1);

namespace Tests\Tools\PHPStan\Fixtures\src\Application;

final class CallsFrameworkFunctions
{
    public function run(): void
    {
        app();
    }
}
