<?php

declare(strict_types=1);

namespace Interfaces\Http\Shared\Controller;

use Application\Shared\UseCase\CheckReadinessUseCase;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class ReadyController
{
    public function __construct(
        private CheckReadinessUseCase $useCase,
    ) {}

    public function __invoke(): JsonResponse
    {
        $readiness = $this->useCase->execute();

        return new JsonResponse(
            [
                'status' => $readiness->isReady() ? 'ok' : 'unavailable',
                'checks' => array_map(
                    static fn(bool $isHealthy): string => $isHealthy ? 'ok' : 'fail',
                    $readiness->checks,
                ),
            ],
            $readiness->isReady() ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }
}
