<?php

declare(strict_types=1);

namespace Interfaces\Http\TravelRequest;

use Application\TravelRequest\Exception\InvalidListCursor;
use Domain\TravelRequest\Entity\TravelRequest;
use Domain\TravelRequest\Exception\InvalidDestination;
use Domain\TravelRequest\Exception\InvalidStatusTransition;
use Domain\TravelRequest\Exception\InvalidTravelPeriod;
use Domain\TravelRequest\Exception\TravelRequestNotEditable;
use Domain\TravelRequest\Exception\TravelRequestNotFound;
use Domain\TravelRequest\Exception\TravelRequestNotOwned;
use Domain\TravelRequest\Exception\TravelRequestVersionMismatch;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Interfaces\Http\Shared\Problem\ProblemDetailsRenderer;
use Interfaces\Http\Shared\Problem\ProblemType;
use Interfaces\Http\Shared\Problem\ProblemTypeRegistry;
use Interfaces\Http\TravelRequest\Policy\TravelRequestPolicy;

final class TravelRequestHttpServiceProvider extends ServiceProvider
{
    public const string SHOW_ROUTE = 'travel-requests.show';

    public const string ID_PARAMETER = 'travelRequestId';

    public function boot(Gate $gate, ProblemTypeRegistry $problemTypes): void
    {
        $gate->policy(TravelRequest::class, TravelRequestPolicy::class);

        $this->registerProblemTypes(problemTypes: $problemTypes);

        Route::middleware('api')
            ->group(base_path('routes/travel_request.php'));
    }

    private function registerProblemTypes(ProblemTypeRegistry $problemTypes): void
    {
        $invalidInput = new ProblemType(
            status: 422,
            type: ProblemDetailsRenderer::VALIDATION_FAILED_TYPE,
            title: 'The request is invalid.',
        );
        // Someone else's request is reported exactly like a missing one, so its id is never confirmed.
        $notFound = new ProblemType(
            status: 404,
            type: '/problems/not-found',
            title: 'The resource was not found.',
            isDetailFromException: false,
        );

        $problemTypes->register(exceptionClass: InvalidDestination::class, problemType: $invalidInput);
        $problemTypes->register(exceptionClass: InvalidTravelPeriod::class, problemType: $invalidInput);
        $problemTypes->register(exceptionClass: InvalidListCursor::class, problemType: $invalidInput);
        $problemTypes->register(exceptionClass: TravelRequestNotFound::class, problemType: $notFound);
        $problemTypes->register(exceptionClass: TravelRequestNotOwned::class, problemType: $notFound);
        $problemTypes->register(
            exceptionClass: InvalidStatusTransition::class,
            problemType: new ProblemType(
                status: 409,
                type: '/problems/invalid-status-transition',
                title: 'The travel request cannot move to that status.',
            ),
        );
        $problemTypes->register(
            exceptionClass: TravelRequestNotEditable::class,
            problemType: new ProblemType(
                status: 409,
                type: '/problems/travel-request-not-editable',
                title: 'The travel request can no longer be edited.',
            ),
        );
        $problemTypes->register(
            exceptionClass: TravelRequestVersionMismatch::class,
            problemType: new ProblemType(
                status: 412,
                type: '/problems/version-mismatch',
                title: 'The travel request changed since it was read.',
            ),
        );
    }
}
