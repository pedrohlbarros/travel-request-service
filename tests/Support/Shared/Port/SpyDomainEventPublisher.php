<?php

declare(strict_types=1);

namespace Tests\Support\Shared\Port;

use Application\Shared\Port\DomainEventPublisher;
use Domain\Shared\Event\DomainEvent;

final class SpyDomainEventPublisher implements DomainEventPublisher
{
    /** @var list<DomainEvent> */
    public private(set) array $publishedEvents = [];

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->publishedEvents[] = $event;
        }
    }
}
