<?php

declare(strict_types=1);

namespace Application\Notification\Port;

use Domain\Notification\ValueObject\TravelRequestDecision;

/**
 * The durable channels (stored notification and e-mail); a failure here makes the whole delivery retry.
 */
interface DecisionNotifier
{
    public function notify(TravelRequestDecision $decision): void;
}
