<?php

declare(strict_types=1);

namespace Application\TravelRequest\Input;

final readonly class GetTravelRequestInput
{
    public function __construct(
        public string $travelRequestId,
        public string $viewerId,
        public bool $isViewerAdmin,
    ) {}
}
