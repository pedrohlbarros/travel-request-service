<?php

declare(strict_types=1);

namespace Application\Notification\Port;

use Domain\Shared\ValueObject\Uuid7;

/**
 * The inbox: remembers which events this consumer already handled, so a redelivery changes nothing.
 */
interface ProcessedEvents
{
    /**
     * Records the event and says whether this is the first time it was seen.
     */
    public function recordFirstDelivery(Uuid7 $eventId): bool;
}
