<?php

declare(strict_types=1);

namespace Interfaces\Http\TravelRequest\Controller;

use Application\TravelRequest\Input\ApproveTravelRequestInput;
use Application\TravelRequest\UseCase\ApproveTravelRequestUseCase;
use Domain\TravelRequest\Exception\InvalidStatusTransition;
use Domain\TravelRequest\Exception\TravelRequestNotFound;
use Domain\TravelRequest\Exception\TravelRequestVersionMismatch;
use Illuminate\Http\Request;
use Interfaces\Http\Shared\Auth\CurrentUser;
use Interfaces\Http\Shared\Concurrency\EntityTag;
use Interfaces\Http\Shared\Concurrency\RespondsWithEntityTag;
use Interfaces\Http\TravelRequest\Resource\TravelRequestResource;

final readonly class ApproveTravelRequestController
{
    public function __construct(
        private ApproveTravelRequestUseCase $approveTravelRequest,
    ) {}

    /**
     * Admins only; a request can only be approved while it is still requested.
     *
     * @throws TravelRequestNotFound
     * @throws TravelRequestVersionMismatch
     * @throws InvalidStatusTransition
     */
    #[RespondsWithEntityTag]
    public function __invoke(Request $request, string $travelRequestId): TravelRequestResource
    {
        $caller = CurrentUser::of(request: $request);
        $decision = new ApproveTravelRequestInput(
            travelRequestId: $travelRequestId,
            approverId: $caller->id,
            expectedVersion: EntityTag::expectedVersionOf(request: $request),
        );
        $travelRequest = $this->approveTravelRequest->execute(input: $decision);

        return new TravelRequestResource(travelRequest: $travelRequest);
    }
}
