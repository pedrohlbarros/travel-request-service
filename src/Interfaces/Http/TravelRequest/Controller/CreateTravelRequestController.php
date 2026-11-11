<?php

declare(strict_types=1);

namespace Interfaces\Http\TravelRequest\Controller;

use Application\TravelRequest\UseCase\CreateTravelRequestUseCase;
use Domain\TravelRequest\Exception\InvalidDestination;
use Domain\TravelRequest\Exception\InvalidTravelPeriod;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\JsonResponse;
use Interfaces\Http\Shared\Auth\CurrentUser;
use Interfaces\Http\Shared\Concurrency\RespondsWithEntityTag;
use Interfaces\Http\TravelRequest\Request\CreateTravelRequestRequest;
use Interfaces\Http\TravelRequest\Resource\TravelRequestResource;
use Interfaces\Http\TravelRequest\TravelRequestHttpServiceProvider;
use Symfony\Component\HttpFoundation\Response;

final readonly class CreateTravelRequestController
{
    private const string LOCATION_HEADER = 'Location';

    public function __construct(
        private CreateTravelRequestUseCase $createTravelRequest,
        private UrlGenerator $urls,
    ) {}

    /**
     * @throws InvalidDestination
     * @throws InvalidTravelPeriod
     */
    #[RespondsWithEntityTag]
    public function __invoke(CreateTravelRequestRequest $request): JsonResponse
    {
        $caller = CurrentUser::of(request: $request);
        $travelRequest = $this->createTravelRequest->execute(input: $request->toInput(requesterId: $caller->id));
        $location = $this->urls->route(
            TravelRequestHttpServiceProvider::SHOW_ROUTE,
            [TravelRequestHttpServiceProvider::ID_PARAMETER => $travelRequest->id],
        );

        return new TravelRequestResource(travelRequest: $travelRequest)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header(self::LOCATION_HEADER, $location);
    }
}
