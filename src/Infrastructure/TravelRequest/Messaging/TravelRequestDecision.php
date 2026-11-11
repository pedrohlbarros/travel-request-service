<?php

declare(strict_types=1);

namespace Infrastructure\TravelRequest\Messaging;

use DateTimeImmutable;
use Domain\TravelRequest\Enum\TravelRequestStatus;
use Domain\TravelRequest\ValueObject\Destination;
use Domain\TravelRequest\ValueObject\TravelPeriod;
use Domain\TravelRequest\ValueObject\TravelRequestId;
use Domain\TravelRequest\ValueObject\UserId;

/**
 * What approval and cancellation events have in common, so both map to the same message shape.
 */
final readonly class TravelRequestDecision
{
    public function __construct(
        public TravelRequestId $travelRequestId,
        public UserId $requesterId,
        public Destination $destination,
        public TravelPeriod $period,
        public TravelRequestStatus $status,
        public int $version,
        public string $deciderField,
        public UserId $deciderId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
