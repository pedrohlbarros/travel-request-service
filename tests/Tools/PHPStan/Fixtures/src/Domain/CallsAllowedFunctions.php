<?php

declare(strict_types=1);

namespace Tests\Tools\PHPStan\Fixtures\src\Domain;

function local_helper(): int
{
    return 1;
}

final class CallsAllowedFunctions
{
    public function run(): int
    {
        $callable = 'strlen';

        return strlen('x') + local_helper() + $callable('y');
    }
}
