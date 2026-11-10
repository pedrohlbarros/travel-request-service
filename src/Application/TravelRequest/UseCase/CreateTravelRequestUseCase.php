<?php

declare(strict_types=1);

namespace Application\TravelRequest\UseCase;

use Application\Shared\Port\Clock;
use Application\Shared\Port\IdentifierGenerator;
use Application\TravelRequest\Input\CreateTravelRequestInput;
use Application\TravelRequest\Output\TravelRequestOutput;
use Domain\Shared\Exception\InvalidIdentifier;
use Domain\TravelRequest\Entity\TravelRequest;
use Domain\TravelRequest\Exception\InvalidDestination;
use Domain\TravelRequest\Exception\InvalidTravelPeriod;
use Domain\TravelRequest\Repository\TravelRequestRepository;
use Domain\TravelRequest\ValueObject\Destination;
use Domain\TravelRequest\ValueObject\TravelPeriod;
use Domain\TravelRequest\ValueObject\TravelRequestId;
use Domain\TravelRequest\ValueObject\UserId;

final readonly class CreateTravelRequestUseCase
{
    public function __construct(
        private TravelRequestRepository $travelRequests,
        private IdentifierGenerator $identifiers,
        private Clock $clock,
    ) {}

    /**
     * @throws InvalidIdentifier
     * @throws InvalidDestination
     * @throws InvalidTravelPeriod
     */
    public function execute(CreateTravelRequestInput $input): TravelRequestOutput
    {
        $travelRequestId = TravelRequestId::fromString(id: $this->identifiers->uuid7());
        $travelRequest = TravelRequest::request(
            id: $travelRequestId,
            requesterId: UserId::fromString(id: $input->requesterId),
            destination: Destination::fromString(name: $input->destination),
            period: TravelPeriod::between(departureDate: $input->departureDate, returnDate: $input->returnDate),
            requestedAt: $this->clock->now(),
        );

        $this->travelRequests->add(travelRequest: $travelRequest);

        return TravelRequestOutput::fromEntity(travelRequest: $travelRequest);
    }
}
