<?php

declare(strict_types=1);

namespace Application\TravelRequest\Port;

use Application\TravelRequest\Input\TravelRequestFilters;
use Domain\TravelRequest\ValueObject\UserId;

final readonly class TravelRequestListCriteria
{
    /**
     * @param ?UserId $requesterId only this requester's requests; null means everyone's
     * @param ?string $cursor opaque position from a previous page; null starts at the newest request
     * @param positive-int $perPage
     */
    public function __construct(
        public ?UserId $requesterId,
        public TravelRequestFilters $filters,
        public ?string $cursor,
        public int $perPage,
    ) {}
}
