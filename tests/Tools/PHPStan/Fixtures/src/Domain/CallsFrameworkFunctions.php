<?php

declare(strict_types=1);

namespace Tests\Tools\PHPStan\Fixtures\src\Domain;

final class CallsFrameworkFunctions
{
    public function run(): void
    {
        now();
        \config('app.name');
        \collect([]);
    }
}
