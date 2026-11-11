<?php

declare(strict_types=1);

namespace Tests\Support\Notification\Port;

use Application\Notification\Port\ProcessedEvents;
use Domain\Shared\ValueObject\Uuid7;

final class InMemoryProcessedEvents implements ProcessedEvents
{
    /** @var list<string> */
    public private(set) array $processedEventIds = [];

    public function recordFirstDelivery(Uuid7 $eventId): bool
    {
        if (\in_array($eventId->value, $this->processedEventIds, true)) {
            return false;
        }

        $this->processedEventIds[] = $eventId->value;

        return true;
    }
}
