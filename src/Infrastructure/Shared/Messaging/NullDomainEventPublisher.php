<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Messaging;

use Application\Shared\Port\DomainEventPublisher;
use Domain\Shared\Event\DomainEvent;

/**
 * Drops the events until they have somewhere to go.
 */
final readonly class NullDomainEventPublisher implements DomainEventPublisher
{
    public function publish(DomainEvent ...$events): void {}
}
