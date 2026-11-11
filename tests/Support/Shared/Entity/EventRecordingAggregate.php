<?php

declare(strict_types=1);

namespace Tests\Support\Shared\Entity;

use Domain\Shared\Entity\RecordsDomainEvents;
use Domain\Shared\Event\DomainEvent;

final class EventRecordingAggregate
{
    use RecordsDomainEvents;

    public function happen(DomainEvent $event): void
    {
        $this->record(event: $event);
    }
}
