<?php

declare(strict_types=1);

namespace Infrastructure\TravelRequest\Observability;

use Domain\TravelRequest\Entity\TravelRequest;
use Domain\TravelRequest\Enum\TravelRequestStatus;
use Domain\TravelRequest\Repository\TravelRequestRepository;
use Domain\TravelRequest\ValueObject\TravelRequestId;
use Illuminate\Database\DatabaseTransactionsManager;
use Infrastructure\Shared\Observability\Telemetry;

/**
 * Counts creations and decisions once they are committed. Only a decision moves a request out of requested,
 * so an update that leaves it approved or cancelled is a transition to that status.
 */
final readonly class MetricsRecordingTravelRequestRepository implements TravelRequestRepository
{
    public function __construct(
        private TravelRequestRepository $travelRequests,
        private Telemetry $telemetry,
        private DatabaseTransactionsManager $transactions,
    ) {}

    public function get(TravelRequestId $id): TravelRequest
    {
        return $this->travelRequests->get(id: $id);
    }

    public function add(TravelRequest $travelRequest): void
    {
        $this->travelRequests->add(travelRequest: $travelRequest);
        $this->transactions->addCallback(function (): void {
            $this->telemetry->meter()
                ->createCounter('travel_request.created', '{request}', 'Travel requests created.')
                ->add(1);
        });
    }

    public function update(TravelRequest $travelRequest, int $loadedVersion): void
    {
        $this->travelRequests->update(travelRequest: $travelRequest, loadedVersion: $loadedVersion);
        $status = $travelRequest->status;

        if ($status === TravelRequestStatus::Requested) {
            return;
        }

        $this->transactions->addCallback(function () use ($status): void {
            $this->telemetry->meter()
                ->createCounter('travel_request.transitions', '{request}', 'Travel requests decided, by status.')
                ->add(1, ['status' => $status->value]);
        });
    }
}
