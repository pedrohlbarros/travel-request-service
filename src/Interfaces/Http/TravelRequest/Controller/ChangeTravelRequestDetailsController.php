<?php

declare(strict_types=1);

namespace Interfaces\Http\TravelRequest\Controller;

use Application\TravelRequest\UseCase\ChangeTravelRequestDetailsUseCase;
use Domain\TravelRequest\Exception\InvalidDestination;
use Domain\TravelRequest\Exception\InvalidTravelPeriod;
use Domain\TravelRequest\Exception\TravelRequestNotEditable;
use Domain\TravelRequest\Exception\TravelRequestNotFound;
use Domain\TravelRequest\Exception\TravelRequestVersionMismatch;
use Interfaces\Http\Shared\Auth\CurrentUser;
use Interfaces\Http\Shared\Concurrency\EntityTag;
use Interfaces\Http\Shared\Concurrency\RespondsWithEntityTag;
use Interfaces\Http\TravelRequest\Request\ChangeTravelRequestDetailsRequest;
use Interfaces\Http\TravelRequest\Resource\TravelRequestResource;

final readonly class ChangeTravelRequestDetailsController
{
    public function __construct(
        private ChangeTravelRequestDetailsUseCase $changeDetails,
    ) {}

    /**
     * Only the requester edits, and only while the request is still requested.
     *
     * @throws TravelRequestNotFound
     * @throws TravelRequestVersionMismatch
     * @throws TravelRequestNotEditable
     * @throws InvalidDestination
     * @throws InvalidTravelPeriod
     */
    #[RespondsWithEntityTag]
    public function __invoke(ChangeTravelRequestDetailsRequest $request, string $travelRequestId): TravelRequestResource
    {
        $caller = CurrentUser::of(request: $request);
        $change = $request->toInput(
            travelRequestId: $travelRequestId,
            editorId: $caller->id,
            expectedVersion: EntityTag::expectedVersionOf(request: $request),
        );
        $travelRequest = $this->changeDetails->execute(input: $change);

        return new TravelRequestResource(travelRequest: $travelRequest);
    }
}
