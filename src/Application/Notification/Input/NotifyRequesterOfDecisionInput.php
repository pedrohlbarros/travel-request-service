<?php

declare(strict_types=1);

namespace Application\Notification\Input;

use DateTimeImmutable;

final readonly class NotifyRequesterOfDecisionInput
{
    public function __construct(
        public string $eventId,
        public string $travelRequestId,
        public string $requesterId,
        public string $requesterName,
        public string $requesterEmail,
        public string $destination,
        public DateTimeImmutable $departureDate,
        public DateTimeImmutable $returnDate,
        public string $outcome,
        public string $decidedBy,
        public DateTimeImmutable $decidedAt,
    ) {}
}
