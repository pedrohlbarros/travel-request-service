<?php

declare(strict_types=1);

namespace Application\Shared\Port;

use Domain\Shared\Event\DomainEvent;

interface DomainEventPublisher
{
    public function publish(DomainEvent ...$events): void;
}
