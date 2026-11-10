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
        $travelRequest = TravelRequest::request(
            TravelRequestId::fromString($this->identifiers->uuid7()),
            UserId::fromString($input->requesterId),
            Destination::fromString($input->destination),
            TravelPeriod::between($input->departureDate, $input->returnDate),
            $this->clock->now(),
        );

        $this->travelRequests->add($travelRequest);

        return TravelRequestOutput::fromEntity($travelRequest);
    }
}
