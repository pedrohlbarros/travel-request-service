<?php

declare(strict_types=1);

namespace Tests\Support\TravelRequest\Port;

use Application\TravelRequest\Output\TravelRequestOutput;
use Application\TravelRequest\Output\TravelRequestPage;
use Application\TravelRequest\Port\TravelRequestListCriteria;
use Application\TravelRequest\Port\TravelRequestQueries;
use Domain\TravelRequest\Exception\TravelRequestNotFound;
use Domain\TravelRequest\ValueObject\TravelRequestId;
use Tests\Support\TravelRequest\Fixture\TravelRequestMother as Mother;
use Tests\Support\TravelRequest\Repository\InMemoryTravelRequestRepository;

/**
 * Reads what the in-memory repository stores, naming users as the fixture does. Lists honour only the requester scope and the page size:
 * filters and cursors are covered against MySQL.
 */
final readonly class InMemoryTravelRequestQueries implements TravelRequestQueries
{
    public function __construct(
        private InMemoryTravelRequestRepository $travelRequests,
    ) {}

    public function find(TravelRequestId $id): ?TravelRequestOutput
    {
        try {
            $travelRequest = $this->travelRequests->get(id: $id);
        } catch (TravelRequestNotFound) {
            return null;
        }

        return Mother::output(travelRequest: $travelRequest);
    }

    public function get(TravelRequestId $id): TravelRequestOutput
    {
        $travelRequest = $this->travelRequests->get(id: $id);

        return Mother::output(travelRequest: $travelRequest);
    }

    public function list(TravelRequestListCriteria $criteria): TravelRequestPage
    {
        $visibleTravelRequests = [];

        foreach ($this->travelRequests->all() as $travelRequest) {
            $isInScope = $criteria->requesterId === null || $travelRequest->requesterId->equals(other: $criteria->requesterId);

            if ($isInScope) {
                $visibleTravelRequests[] = Mother::output(travelRequest: $travelRequest);
            }
        }

        usort(
            $visibleTravelRequests,
            static fn(TravelRequestOutput $left, TravelRequestOutput $right): int => strcmp($right->id, $left->id),
        );

        return new TravelRequestPage(
            travelRequests: \array_slice($visibleTravelRequests, 0, $criteria->perPage),
            perPage: $criteria->perPage,
            nextCursor: null,
            previousCursor: null,
        );
    }
}
