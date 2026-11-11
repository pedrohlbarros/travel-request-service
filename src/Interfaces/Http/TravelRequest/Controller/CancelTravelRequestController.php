<?php

declare(strict_types=1);

namespace Interfaces\Http\TravelRequest\Controller;

use Application\TravelRequest\Input\CancelTravelRequestInput;
use Application\TravelRequest\UseCase\CancelTravelRequestUseCase;
use Domain\TravelRequest\Exception\InvalidStatusTransition;
use Domain\TravelRequest\Exception\TravelRequestNotFound;
use Domain\TravelRequest\Exception\TravelRequestVersionMismatch;
use Illuminate\Http\Request;
use Interfaces\Http\Shared\Auth\CurrentUser;
use Interfaces\Http\Shared\Concurrency\EntityTag;
use Interfaces\Http\Shared\Concurrency\RespondsWithEntityTag;
use Interfaces\Http\TravelRequest\Resource\TravelRequestResource;

final readonly class CancelTravelRequestController
{
    public function __construct(
        private CancelTravelRequestUseCase $cancelTravelRequest,
    ) {}

    /**
     * Admins only; an approved request can no longer be cancelled.
     *
     * @throws TravelRequestNotFound
     * @throws TravelRequestVersionMismatch
     * @throws InvalidStatusTransition
     */
    #[RespondsWithEntityTag]
    public function __invoke(Request $request, string $travelRequestId): TravelRequestResource
    {
        $caller = CurrentUser::of(request: $request);
        $decision = new CancelTravelRequestInput(
            travelRequestId: $travelRequestId,
            cancellerId: $caller->id,
            expectedVersion: EntityTag::expectedVersionOf(request: $request),
        );
        $travelRequest = $this->cancelTravelRequest->execute(input: $decision);

        return new TravelRequestResource(travelRequest: $travelRequest);
    }
}
