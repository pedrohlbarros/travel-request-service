<?php

declare(strict_types=1);

namespace Application\TravelRequest\Input;

final readonly class ApproveTravelRequestInput
{
    public function __construct(
        public string $travelRequestId,
        public string $approverId,
        public int $expectedVersion,
    ) {}
}
