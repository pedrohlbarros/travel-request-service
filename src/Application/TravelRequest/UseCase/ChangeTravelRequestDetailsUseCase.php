<?php

declare(strict_types=1);

namespace Application\TravelRequest\UseCase;

use Application\Shared\Port\Clock;
use Application\TravelRequest\Input\ChangeTravelRequestDetailsInput;
use Application\TravelRequest\Output\TravelRequestOutput;
use Application\TravelRequest\Port\TravelRequestQueries;
use Domain\Shared\Exception\InvalidIdentifier;
use Domain\TravelRequest\Exception\InvalidDestination;
use Domain\TravelRequest\Exception\InvalidTravelPeriod;
use Domain\TravelRequest\Exception\TravelRequestNotEditable;
use Domain\TravelRequest\Exception\TravelRequestNotFound;
use Domain\TravelRequest\Exception\TravelRequestNotOwned;
use Domain\TravelRequest\Exception\TravelRequestVersionMismatch;
use Domain\TravelRequest\Repository\TravelRequestRepository;
use Domain\TravelRequest\ValueObject\Destination;
use Domain\TravelRequest\ValueObject\TravelRequestId;
use Domain\TravelRequest\ValueObject\UserId;

final readonly class ChangeTravelRequestDetailsUseCase
{
    public function __construct(
        private TravelRequestRepository $travelRequests,
        private TravelRequestQueries $queries,
        private Clock $clock,
    ) {}

    /**
     * Ownership is checked before the version, so a wrong version never confirms that someone else's request
     * exists. A change to the same details stores nothing and keeps the version.
     *
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
        $editorId = UserId::fromString(id: $input->editorId);
        $travelRequest->assertOwnedBy(userId: $editorId);
        $loadedVersion = $travelRequest->version;
        $travelRequest->assertVersionIs(expectedVersion: $input->expectedVersion);

        $travelRequest->changeDetails(
            editorId: $editorId,
            destination: $this->destinationFrom(input: $input),
            departureDate: $input->departureDate,
            returnDate: $input->returnDate,
            changedAt: $this->clock->now(),
        );

        if ($travelRequest->version !== $loadedVersion) {
            $this->travelRequests->update(travelRequest: $travelRequest, loadedVersion: $loadedVersion);
        }

        return $this->queries->get(id: $travelRequest->id);
    }

    /**
     * @throws InvalidDestination
     */
    private function destinationFrom(ChangeTravelRequestDetailsInput $input): ?Destination
    {
        if ($input->destination === null) {
            return null;
        }

        return Destination::fromString(name: $input->destination);
    }
}
