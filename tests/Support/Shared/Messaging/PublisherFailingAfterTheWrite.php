<?php

declare(strict_types=1);

namespace Tests\Support\Shared\Messaging;

use Application\Shared\Port\DomainEventPublisher;
use Domain\Shared\Event\DomainEvent;
use RuntimeException;

/**
 * Writes the events and then fails, as a later write in the same transaction would.
 */
final readonly class PublisherFailingAfterTheWrite implements DomainEventPublisher
{
    public const string FAILURE = 'The write after the outbox failed.';

    public function __construct(
        private DomainEventPublisher $outbox,
    ) {}

    public function publish(DomainEvent ...$events): void
    {
        $this->outbox->publish(...$events);

        throw new RuntimeException(self::FAILURE);
    }
}
