<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Messaging\Outbox;

use Domain\Shared\Event\DomainEvent;

/**
 * Each context maps its own events, so the shared outbox never knows any of them.
 */
interface EventMessageMapper
{
    public function supports(DomainEvent $event): bool;

    public function toMessage(DomainEvent $event): EventMessage;
}
