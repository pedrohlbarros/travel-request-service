<?php

declare(strict_types=1);

namespace Tests\Support\Notification\Port;

use Application\Notification\Port\DecisionNotifier;
use Domain\Notification\ValueObject\TravelRequestDecision;
use RuntimeException;

final class SpyDecisionNotifier implements DecisionNotifier
{
    public const string FAILURE = 'The mail server is down.';

    /** @var list<TravelRequestDecision> */
    public private(set) array $notifiedDecisions = [];

    public function __construct(
        private readonly bool $isFailing = false,
    ) {}

    public function notify(TravelRequestDecision $decision): void
    {
        if ($this->isFailing) {
            throw new RuntimeException(self::FAILURE);
        }

        $this->notifiedDecisions[] = $decision;
    }
}
