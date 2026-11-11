<?php

declare(strict_types=1);

namespace Application\TravelRequest\Output;

use DateTimeImmutable;
use Domain\TravelRequest\Enum\TravelRequestStatus;

/**
 * Built by the read side, which also knows the names of the users the request refers to.
 */
final readonly class TravelRequestOutput
{
    public function __construct(
        public string $id,
        public UserReferenceOutput $requester,
        public string $destination,
        public DateTimeImmutable $departureDate,
        public DateTimeImmutable $returnDate,
        public TravelRequestStatus $status,
        public int $version,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?UserReferenceOutput $decidedBy,
    ) {}
}
