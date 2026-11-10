<?php

declare(strict_types=1);

namespace Application\TravelRequest\Output;

use DateTimeImmutable;
use Domain\TravelRequest\Entity\TravelRequest;
use Domain\TravelRequest\Enum\TravelRequestStatus;

final readonly class TravelRequestOutput
{
    public function __construct(
        public string $id,
        public string $requesterId,
        public string $destination,
        public DateTimeImmutable $departureDate,
        public DateTimeImmutable $returnDate,
        public TravelRequestStatus $status,
        public int $version,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?string $decidedBy,
    ) {}

    public static function fromEntity(TravelRequest $travelRequest): self
    {
        return new self(
            id: $travelRequest->id->toString(),
            requesterId: $travelRequest->requesterId->toString(),
            destination: $travelRequest->destination->name,
            departureDate: $travelRequest->period->departureDate,
            returnDate: $travelRequest->period->returnDate,
            status: $travelRequest->status,
            version: $travelRequest->version,
            createdAt: $travelRequest->createdAt,
            updatedAt: $travelRequest->updatedAt,
            decidedBy: $travelRequest->decidedBy?->toString(),
        );
    }
}
