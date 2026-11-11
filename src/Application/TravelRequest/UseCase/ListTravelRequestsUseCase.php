<?php

declare(strict_types=1);

namespace Application\TravelRequest\UseCase;

use Application\TravelRequest\Exception\InvalidListCursor;
use Application\TravelRequest\Input\ListTravelRequestsInput;
use Application\TravelRequest\Output\TravelRequestPage;
use Application\TravelRequest\Port\TravelRequestListCriteria;
use Application\TravelRequest\Port\TravelRequestQueries;
use Domain\Shared\Exception\InvalidIdentifier;
use Domain\TravelRequest\ValueObject\UserId;

final readonly class ListTravelRequestsUseCase
{
    public function __construct(
        private TravelRequestQueries $queries,
    ) {}

    /**
     * Admins list everyone's requests; anyone else only their own.
     *
     * @throws InvalidIdentifier
     * @throws InvalidListCursor
     */
    public function execute(ListTravelRequestsInput $input): TravelRequestPage
    {
        $requesterId = $input->isViewerAdmin ? null : UserId::fromString(id: $input->viewerId);
        $criteria = new TravelRequestListCriteria(
            requesterId: $requesterId,
            filters: $input->filters,
            cursor: $input->cursor,
            perPage: $input->perPage,
        );

        return $this->queries->list(criteria: $criteria);
    }
}
