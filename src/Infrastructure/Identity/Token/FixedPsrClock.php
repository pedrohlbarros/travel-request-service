<?php

declare(strict_types=1);

namespace Infrastructure\Identity\Token;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

final readonly class FixedPsrClock implements ClockInterface
{
    public function __construct(
        private DateTimeImmutable $now,
    ) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
