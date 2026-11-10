<?php

declare(strict_types=1);

namespace Application\TravelRequest\Input;

use DateTimeImmutable;

final readonly class CreateTravelRequestInput
{
    public function __construct(
        public string $requesterId,
        public string $destination,
        public DateTimeImmutable $departureDate,
        public DateTimeImmutable $returnDate,
    ) {}
}
