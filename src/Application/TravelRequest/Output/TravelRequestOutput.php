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
            $travelRequest->id->toString(),
            $travelRequest->requesterId->toString(),
            $travelRequest->destination->name,
            $travelRequest->period->departureDate,
            $travelRequest->period->returnDate,
            $travelRequest->status,
            $travelRequest->version,
            $travelRequest->createdAt,
            $travelRequest->updatedAt,
            $travelRequest->decidedBy?->toString(),
        );
    }
}
