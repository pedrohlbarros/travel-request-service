<?php

declare(strict_types=1);

namespace Application\TravelRequest\UseCase;

use Application\TravelRequest\Input\GetTravelRequestInput;
use Application\TravelRequest\Output\TravelRequestOutput;
use Application\TravelRequest\Port\TravelRequestQueries;
use Domain\Shared\Exception\InvalidIdentifier;
use Domain\TravelRequest\Exception\TravelRequestNotFound;
use Domain\TravelRequest\ValueObject\TravelRequestId;

final readonly class GetTravelRequestUseCase
{
    public function __construct(
        private TravelRequestQueries $queries,
    ) {}

    /**
     * Someone else's request is reported as not found, so its id is never confirmed to exist.
     *
     * @throws InvalidIdentifier
     * @throws TravelRequestNotFound
     */
    public function execute(GetTravelRequestInput $input): TravelRequestOutput
    {
        $travelRequestId = TravelRequestId::fromString(id: $input->travelRequestId);
        $travelRequest = $this->queries->find(id: $travelRequestId);

        if ($travelRequest === null) {
            throw TravelRequestNotFound::withId(id: $travelRequestId);
        }

        if ($travelRequest->requester->id !== $input->viewerId && ! $input->isViewerAdmin) {
            throw TravelRequestNotFound::withId(id: $travelRequestId);
        }

        return $travelRequest;
    }
}
