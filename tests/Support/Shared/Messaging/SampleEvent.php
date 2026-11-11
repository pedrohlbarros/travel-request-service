<?php

declare(strict_types=1);

namespace Tests\Support\Shared\Messaging;

use DateTimeImmutable;
use Domain\Shared\Event\DomainEvent;

final readonly class SampleEvent implements DomainEvent
{
    public function __construct(
        public string $subject,
        public DateTimeImmutable $occurredAt,
    ) {}
}
