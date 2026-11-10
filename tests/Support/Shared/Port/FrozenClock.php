<?php

declare(strict_types=1);

namespace Tests\Support\Shared\Port;

use Application\Shared\Port\Clock;
use DateTimeImmutable;

final class FrozenClock implements Clock
{
    public function __construct(
        private DateTimeImmutable $frozenAt,
    ) {}

    public function now(): DateTimeImmutable
    {
        return $this->frozenAt;
    }

    public function travelTo(DateTimeImmutable $moment): void
    {
        $this->frozenAt = $moment;
    }
}
