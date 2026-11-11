<?php

declare(strict_types=1);

namespace Application\Notification\Port;

use Domain\Notification\ValueObject\TravelRequestDecision;

/**
 * The real-time channel, best effort: it never fails the delivery, since the stored notification stays readable.
 */
interface DecisionBroadcaster
{
    public function broadcast(TravelRequestDecision $decision): void;
}
