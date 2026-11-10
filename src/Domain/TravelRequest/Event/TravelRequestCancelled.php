<?php

declare(strict_types=1);

namespace Domain\TravelRequest\Event;

use DateTimeImmutable;
use Domain\Shared\Event\DomainEvent;
use Domain\TravelRequest\ValueObject\Destination;
use Domain\TravelRequest\ValueObject\TravelPeriod;
use Domain\TravelRequest\ValueObject\TravelRequestId;
use Domain\TravelRequest\ValueObject\UserId;

final readonly class TravelRequestCancelled implements DomainEvent
{
    public function __construct(
        public TravelRequestId $travelRequestId,
        public UserId $requesterId,
        public UserId $cancelledBy,
        public Destination $destination,
        public TravelPeriod $period,
        public int $version,
        public DateTimeImmutable $occurredAt,
    ) {}
}
