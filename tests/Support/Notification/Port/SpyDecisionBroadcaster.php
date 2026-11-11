<?php

declare(strict_types=1);

namespace Tests\Support\Notification\Port;

use Application\Notification\Port\DecisionBroadcaster;
use Domain\Notification\ValueObject\TravelRequestDecision;

final class SpyDecisionBroadcaster implements DecisionBroadcaster
{
    /** @var list<TravelRequestDecision> */
    public private(set) array $broadcastDecisions = [];

    public function broadcast(TravelRequestDecision $decision): void
    {
        $this->broadcastDecisions[] = $decision;
    }
}
