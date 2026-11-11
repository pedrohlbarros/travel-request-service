<?php

declare(strict_types=1);

namespace Application\TravelRequest\Input;

use DateTimeImmutable;
use Domain\TravelRequest\Enum\TravelRequestStatus;

/**
 * Every filter is optional; the ones given are combined with AND.
 */
final readonly class TravelRequestFilters
{
    /**
     * @param list<TravelRequestStatus> $statuses any of them matches; empty means every status
     * @param ?string $destination matches when the destination contains it, ignoring case
     * @param ?DateTimeImmutable $createdFrom first creation day, inclusive
     * @param ?DateTimeImmutable $createdTo last creation day, inclusive
     * @param ?DateTimeImmutable $travelFrom the trip must not end before this day
     * @param ?DateTimeImmutable $travelTo the trip must not start after this day
     */
    public function __construct(
        public array $statuses = [],
        public ?string $destination = null,
        public ?DateTimeImmutable $createdFrom = null,
        public ?DateTimeImmutable $createdTo = null,
        public ?DateTimeImmutable $travelFrom = null,
        public ?DateTimeImmutable $travelTo = null,
    ) {}
}
