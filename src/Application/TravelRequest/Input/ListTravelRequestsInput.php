<?php

declare(strict_types=1);

namespace Application\TravelRequest\Input;

final readonly class ListTravelRequestsInput
{
    /**
     * @param positive-int $perPage
     */
    public function __construct(
        public string $viewerId,
        public bool $isViewerAdmin,
        public TravelRequestFilters $filters,
        public ?string $cursor,
        public int $perPage,
    ) {}
}
