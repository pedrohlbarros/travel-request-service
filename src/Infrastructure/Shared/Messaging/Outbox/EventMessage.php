<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Messaging\Outbox;

use DateTimeImmutable;

/**
 * What a context says about one of its domain events; the outbox wraps it in the CloudEvents envelope.
 */
final readonly class EventMessage
{
    /**
     * @param array<string, mixed> $eventData
     */
    public function __construct(
        public string $exchange,
        public string $routingKey,
        public string $subject,
        public array $eventData,
        public DateTimeImmutable $occurredAt,
    ) {}
}
