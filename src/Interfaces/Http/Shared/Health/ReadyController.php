<?php

declare(strict_types=1);

namespace Interfaces\Http\Shared\Health;

use Application\Shared\Health\CheckReadiness;
use Application\Shared\Health\CheckReadinessHandler;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class ReadyController
{
    public function __construct(
        private CheckReadinessHandler $handler,
    ) {}

    public function __invoke(): JsonResponse
    {
        $report = $this->handler->handle(new CheckReadiness());

        return new JsonResponse(
            [
                'status' => $report->isReady() ? 'ok' : 'unavailable',
                'checks' => array_map(
                    static fn(bool $isHealthy): string => $isHealthy ? 'ok' : 'fail',
                    $report->checks,
                ),
            ],
            $report->isReady() ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }
}
