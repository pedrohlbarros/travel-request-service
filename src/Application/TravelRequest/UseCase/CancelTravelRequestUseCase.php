<?php

declare(strict_types=1);

namespace Application\TravelRequest\UseCase;

use Application\Shared\Port\Clock;
use Application\Shared\Port\DomainEventPublisher;
use Application\Shared\Port\Transaction;
use Application\TravelRequest\Input\CancelTravelRequestInput;
use Application\TravelRequest\Output\TravelRequestOutput;
use Application\TravelRequest\Port\TravelRequestQueries;
use Domain\Shared\Exception\InvalidIdentifier;
use Domain\TravelRequest\Exception\InvalidStatusTransition;
use Domain\TravelRequest\Exception\TravelRequestNotFound;
use Domain\TravelRequest\Exception\TravelRequestVersionMismatch;
use Domain\TravelRequest\Repository\TravelRequestRepository;
use Domain\TravelRequest\ValueObject\TravelRequestId;
use Domain\TravelRequest\ValueObject\UserId;

final readonly class CancelTravelRequestUseCase
{
    public function __construct(
        private TravelRequestRepository $travelRequests,
        private TravelRequestQueries $queries,
        private DomainEventPublisher $events,
        private Transaction $transaction,
        private Clock $clock,
    ) {}

    /**
     * @throws InvalidIdentifier
     * @throws TravelRequestNotFound
     * @throws TravelRequestVersionMismatch
     * @throws InvalidStatusTransition
     */
    public function execute(CancelTravelRequestInput $input): TravelRequestOutput
    {
        $travelRequest = $this->travelRequests->get(id: TravelRequestId::fromString(id: $input->travelRequestId));
        $loadedVersion = $travelRequest->version;
        $travelRequest->assertVersionIs(expectedVersion: $input->expectedVersion);

        $travelRequest->cancel(
            cancellerId: UserId::fromString(id: $input->cancellerId),
            cancelledAt: $this->clock->now(),
        );

        $this->transaction->run(work: function () use ($travelRequest, $loadedVersion): void {
            $this->travelRequests->update(travelRequest: $travelRequest, loadedVersion: $loadedVersion);
            $this->events->publish(...$travelRequest->releaseEvents());
        });

        return $this->queries->get(id: $travelRequest->id);
    }
}
