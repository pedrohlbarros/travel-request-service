<?php

declare(strict_types=1);

namespace Application\Shared\Health;

interface HealthCheck
{
    public function name(): string;

    public function isHealthy(): bool;
}
