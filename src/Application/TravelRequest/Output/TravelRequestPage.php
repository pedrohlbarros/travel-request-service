<?php

declare(strict_types=1);

namespace Application\TravelRequest\Output;

final readonly class TravelRequestPage
{
    /**
     * @param list<TravelRequestOutput> $travelRequests
     * @param positive-int $perPage
     * @param ?string $nextCursor null on the last page
     * @param ?string $previousCursor null on the first page
     */
    public function __construct(
        public array $travelRequests,
        public int $perPage,
        public ?string $nextCursor,
        public ?string $previousCursor,
    ) {}
}
