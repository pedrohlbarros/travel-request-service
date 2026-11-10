<?php

declare(strict_types=1);

namespace Application\Shared\UseCase;

use Application\Shared\Output\ReadinessOutput;
use Application\Shared\Port\HealthCheck;

final readonly class CheckReadinessUseCase
{
    /**
     * @param iterable<HealthCheck> $checks
     */
    public function __construct(
        private iterable $checks,
    ) {}

    public function execute(): ReadinessOutput
    {
        $isHealthyByCheckName = [];

        foreach ($this->checks as $check) {
            $isHealthyByCheckName[$check->name()] = $check->isHealthy();
        }

        return new ReadinessOutput(checks: $isHealthyByCheckName);
    }
}
