<?php

declare(strict_types=1);

namespace Application\TravelRequest\Input;

use DateTimeImmutable;

/**
 * A null detail is one the client did not send, so it keeps its current value.
 */
final readonly class ChangeTravelRequestDetailsInput
{
    public function __construct(
        public string $travelRequestId,
        public string $editorId,
        public int $expectedVersion,
        public ?string $destination,
        public ?DateTimeImmutable $departureDate,
        public ?DateTimeImmutable $returnDate,
    ) {}
}
