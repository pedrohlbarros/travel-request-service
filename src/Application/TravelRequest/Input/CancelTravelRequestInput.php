<?php

declare(strict_types=1);

namespace Application\TravelRequest\Input;

final readonly class CancelTravelRequestInput
{
    public function __construct(
        public string $travelRequestId,
        public string $cancellerId,
        public int $expectedVersion,
    ) {}
}
