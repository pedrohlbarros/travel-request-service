<?php

declare(strict_types=1);

namespace Tests\Tools\PHPStan\Fixtures\ExplicitFlow;

final class ArgumentLines
{
    public function sameLine(): string
    {
        return str_replace('a', 'b', 'abc');
    }

    public function split(): string
    {
        return str_replace(
            'a',
            'b',
            'abc',
        );
    }

    public function singleArgument(): string
    {
        return strtoupper('abc');
    }
}
