<?php

declare(strict_types=1);

namespace Interfaces\Http\TravelRequest\Controller;

use Application\TravelRequest\Input\GetTravelRequestInput;
use Application\TravelRequest\UseCase\GetTravelRequestUseCase;
use Domain\TravelRequest\Entity\TravelRequest;
use Domain\TravelRequest\Exception\TravelRequestNotFound;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Request;
use Interfaces\Http\Shared\Auth\CurrentUser;
use Interfaces\Http\Shared\Concurrency\RespondsWithEntityTag;
use Interfaces\Http\TravelRequest\Policy\TravelRequestPolicy;
use Interfaces\Http\TravelRequest\Resource\TravelRequestResource;

final readonly class ShowTravelRequestController
{
    public function __construct(
        private GetTravelRequestUseCase $getTravelRequest,
        private Gate $gate,
    ) {}

    /**
     * Someone else's request answers 404, as if it did not exist; admins see any request.
     *
     * @throws TravelRequestNotFound
     */
    #[RespondsWithEntityTag]
    public function __invoke(Request $request, string $travelRequestId): TravelRequestResource
    {
        $caller = CurrentUser::of(request: $request);
        $isViewerAdmin = $this->gate->forUser($caller)
            ->allows(TravelRequestPolicy::VIEW_ALL, TravelRequest::class);
        $travelRequest = $this->getTravelRequest->execute(input: new GetTravelRequestInput(
            travelRequestId: $travelRequestId,
            viewerId: $caller->id,
            isViewerAdmin: $isViewerAdmin,
        ));

        return new TravelRequestResource(travelRequest: $travelRequest);
    }
}
