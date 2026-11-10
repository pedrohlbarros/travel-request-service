<?php

declare(strict_types=1);

namespace Application\TravelRequest\Input;

use DateTimeImmutable;

final readonly class ChangeTravelRequestDetailsInput
{
    public function __construct(
        public string $travelRequestId,
        public string $editorId,
        public int $expectedVersion,
        public string $destination,
        public DateTimeImmutable $departureDate,
        public DateTimeImmutable $returnDate,
    ) {}
}
