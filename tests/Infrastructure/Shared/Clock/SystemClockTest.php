<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Shared\Clock;

use DateTimeImmutable;
use Infrastructure\Shared\Clock\SystemClock;
use PHPUnit\Framework\TestCase;

final class SystemClockTest extends TestCase
{
    public function test_TellsTheCurrentTimeInUtc(): void
    {
        $before = new DateTimeImmutable();

        $now = new SystemClock()->now();

        self::assertSame(
            'UTC',
            $now->getTimezone()
                ->getName(),
        );
        self::assertGreaterThanOrEqual($before->getTimestamp(), $now->getTimestamp());
        self::assertLessThanOrEqual(new DateTimeImmutable()->getTimestamp(), $now->getTimestamp());
    }
}
