<?php

declare(strict_types=1);

namespace Application\Shared\Port;

interface HealthCheck
{
    public function name(): string;

    public function isHealthy(): bool;
}
