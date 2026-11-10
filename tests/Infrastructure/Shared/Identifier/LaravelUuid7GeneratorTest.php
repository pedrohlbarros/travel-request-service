<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Shared\Identifier;

use Domain\Shared\ValueObject\Uuid7;
use Infrastructure\Shared\Identifier\LaravelUuid7Generator;
use PHPUnit\Framework\TestCase;

final class LaravelUuid7GeneratorTest extends TestCase
{
    public function test_GeneratesDistinctUuidsTheDomainAccepts(): void
    {
        $generator = new LaravelUuid7Generator();

        $first = $generator->uuid7();
        $second = $generator->uuid7();

        self::assertSame($first, Uuid7::fromString(candidate: $first)->value);
        self::assertNotSame($first, $second);
    }
}
