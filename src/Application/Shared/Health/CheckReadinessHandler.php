<?php

declare(strict_types=1);

namespace Application\Shared\Health;

final readonly class CheckReadinessHandler
{
    /**
     * @param iterable<HealthCheck> $checks
     */
    public function __construct(
        private iterable $checks,
    ) {}

    public function handle(CheckReadiness $query): ReadinessReport
    {
        $isHealthyByCheckName = [];

        foreach ($this->checks as $check) {
            $isHealthyByCheckName[$check->name()] = $check->isHealthy();
        }

        return new ReadinessReport($isHealthyByCheckName);
    }
}
