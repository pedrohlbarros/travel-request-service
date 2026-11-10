<?php

declare(strict_types=1);

namespace Application\TravelRequest\UseCase;

use Application\Shared\Port\Clock;
use Application\Shared\Port\DomainEventPublisher;
use Application\TravelRequest\Input\ApproveTravelRequestInput;
use Application\TravelRequest\Output\TravelRequestOutput;
use Domain\Shared\Exception\InvalidIdentifier;
use Domain\TravelRequest\Exception\InvalidStatusTransition;
use Domain\TravelRequest\Exception\TravelRequestNotFound;
use Domain\TravelRequest\Exception\TravelRequestVersionMismatch;
use Domain\TravelRequest\Repository\TravelRequestRepository;
use Domain\TravelRequest\ValueObject\TravelRequestId;
use Domain\TravelRequest\ValueObject\UserId;

final readonly class ApproveTravelRequestUseCase
{
    public function __construct(
        private TravelRequestRepository $travelRequests,
        private DomainEventPublisher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws InvalidIdentifier
     * @throws TravelRequestNotFound
     * @throws TravelRequestVersionMismatch
     * @throws InvalidStatusTransition
     */
    public function execute(ApproveTravelRequestInput $input): TravelRequestOutput
    {
        $travelRequest = $this->travelRequests->get(id: TravelRequestId::fromString(id: $input->travelRequestId));
        $loadedVersion = $travelRequest->version;
        $travelRequest->assertVersionIs(expectedVersion: $input->expectedVersion);

        $travelRequest->approve(
            approverId: UserId::fromString(id: $input->approverId),
            approvedAt: $this->clock->now(),
        );

        $this->travelRequests->update(
            travelRequest: $travelRequest,
            loadedVersion: $loadedVersion,
        );
        $this->events->publish(...$travelRequest->releaseEvents());

        return TravelRequestOutput::fromEntity(travelRequest: $travelRequest);
    }
}
