<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Clock;

use Application\Shared\Port\Clock;
use DateTimeImmutable;
use DateTimeZone;

final readonly class SystemClock implements Clock
{
    private const string TIMEZONE = 'UTC';

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone(self::TIMEZONE));
    }
}
