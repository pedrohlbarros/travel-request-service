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
        $travelRequest = $this->travelRequests->get(TravelRequestId::fromString($input->travelRequestId));
        $loadedVersion = $travelRequest->version;
        $travelRequest->assertVersionIs($input->expectedVersion);

        $travelRequest->changeDetails(
            UserId::fromString($input->editorId),
            Destination::fromString($input->destination),
            TravelPeriod::between($input->departureDate, $input->returnDate),
            $this->clock->now(),
        );

        $this->travelRequests->update($travelRequest, $loadedVersion);

        return TravelRequestOutput::fromEntity($travelRequest);
    }
}
