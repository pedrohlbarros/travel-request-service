<?php

declare(strict_types=1);

namespace Application\TravelRequest\UseCase;

use Application\TravelRequest\Input\GetTravelRequestInput;
use Application\TravelRequest\Output\TravelRequestOutput;
use Domain\Shared\Exception\InvalidIdentifier;
use Domain\TravelRequest\Exception\TravelRequestNotFound;
use Domain\TravelRequest\Repository\TravelRequestRepository;
use Domain\TravelRequest\ValueObject\TravelRequestId;
use Domain\TravelRequest\ValueObject\UserId;

final readonly class GetTravelRequestUseCase
{
    public function __construct(
        private TravelRequestRepository $travelRequests,
    ) {}

    /**
     * Someone else's request is reported as not found, so its id is never confirmed to exist.
     *
     * @throws InvalidIdentifier
     * @throws TravelRequestNotFound
     */
    public function execute(GetTravelRequestInput $input): TravelRequestOutput
    {
        $travelRequest = $this->travelRequests->get(TravelRequestId::fromString($input->travelRequestId));
        $isOwnRequest = $travelRequest->requesterId->equals(UserId::fromString($input->viewerId));

        if (! $isOwnRequest && ! $input->isViewerAdmin) {
            throw TravelRequestNotFound::withId($travelRequest->id);
        }

        return TravelRequestOutput::fromEntity($travelRequest);
    }
}
