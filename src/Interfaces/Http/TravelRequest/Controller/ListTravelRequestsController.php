<?php

declare(strict_types=1);

namespace Interfaces\Http\TravelRequest\Controller;

use Application\Shared\Exception\InvalidListCursor;
use Application\TravelRequest\UseCase\ListTravelRequestsUseCase;
use Domain\TravelRequest\Entity\TravelRequest;
use Illuminate\Contracts\Auth\Access\Gate;
use Interfaces\Http\Shared\Auth\CurrentUser;
use Interfaces\Http\TravelRequest\Policy\TravelRequestPolicy;
use Interfaces\Http\TravelRequest\Request\ListTravelRequestsRequest;
use Interfaces\Http\TravelRequest\Resource\TravelRequestPageResource;

final readonly class ListTravelRequestsController
{
    public function __construct(
        private ListTravelRequestsUseCase $listTravelRequests,
        private Gate $gate,
    ) {}

    /**
     * Admins see everyone's requests; anyone else only their own. Newest first.
     *
     * @throws InvalidListCursor
     */
    public function __invoke(ListTravelRequestsRequest $request): TravelRequestPageResource
    {
        $caller = CurrentUser::of(request: $request);
        $isViewerAdmin = $this->gate->forUser($caller)
            ->allows(TravelRequestPolicy::VIEW_ALL, TravelRequest::class);
        $page = $this->listTravelRequests->execute(input: $request->toInput(
            viewerId: $caller->id,
            isViewerAdmin: $isViewerAdmin,
        ));

        return new TravelRequestPageResource(page: $page);
    }
}
