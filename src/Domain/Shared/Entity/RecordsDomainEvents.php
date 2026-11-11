<?php

declare(strict_types=1);

namespace Domain\Shared\Entity;

use Domain\Shared\Event\DomainEvent;

/**
 * Aggregates record what happened while they change; whoever persists them releases the events once.
 */
trait RecordsDomainEvents
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    /**
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $releasedEvents = $this->recordedEvents;
        $this->recordedEvents = [];

        return $releasedEvents;
    }

    private function record(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }
}
