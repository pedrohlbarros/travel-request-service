<?php

declare(strict_types=1);

namespace Application\TravelRequest\UseCase;

use Application\Shared\Port\Clock;
use Application\TravelRequest\Input\ChangeTravelRequestDetailsInput;
use Application\TravelRequest\Output\TravelRequestOutput;
use Domain\Shared\Exception\InvalidIdentifier;
use Domain\TravelRequest\Exception\InvalidDestination;
use Domain\TravelRequest\Exception\InvalidTravelPeriod;
use Domain\TravelRequest\Exception\TravelRequestNotEditable;
use Domain\TravelRequest\Exception\TravelRequestNotFound;
use Domain\TravelRequest\Exception\TravelRequestNotOwned;
use Domain\TravelRequest\Exception\TravelRequestVersionMismatch;
use Domain\TravelRequest\Repository\TravelRequestRepository;
use Domain\TravelRequest\ValueObject\Destination;
use Domain\TravelRequest\ValueObject\TravelPeriod;
use Domain\TravelRequest\ValueObject\TravelRequestId;
use Domain\TravelRequest\ValueObject\UserId;

final readonly class ChangeTravelRequestDetailsUseCase
{
    public function __construct(
        private TravelRequestRepository $travelRequests,
        private Clock $clock,
    ) {}

    /**
     * @throws InvalidIdentifier
     * @throws InvalidDestination
     * @throws InvalidTravelPeriod
     * @throws TravelRequestNotFound
     * @throws TravelRequestNotOwned
     * @throws TravelRequestNotEditable
     * @throws TravelRequestVersionMismatch
     */
    public function execute(ChangeTravelRequestDetailsInput $input): TravelRequestOutput
    {
        $travelRequest = $this->travelRequests->get(id: TravelRequestId::fromString(id: $input->travelRequestId));
        $loadedVersion = $travelRequest->version;
        $travelRequest->assertVersionIs(expectedVersion: $input->expectedVersion);

        $travelRequest->changeDetails(
            editorId: UserId::fromString(id: $input->editorId),
            destination: Destination::fromString(name: $input->destination),
            period: TravelPeriod::between(departureDate: $input->departureDate, returnDate: $input->returnDate),
            changedAt: $this->clock->now(),
        );

        $this->travelRequests->update(travelRequest: $travelRequest, loadedVersion: $loadedVersion);

        return TravelRequestOutput::fromEntity(travelRequest: $travelRequest);
    }
}
