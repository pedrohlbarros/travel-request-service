<?php

declare(strict_types=1);

namespace Tests\Support\Shared\Port;

use Application\Shared\Port\HealthCheck;

final readonly class FixedHealthCheck implements HealthCheck
{
    public function __construct(
        private string $name,
        private bool $isHealthy,
    ) {}

    public static function healthy(string $name): self
    {
        return new self(
            name: $name,
            isHealthy: true,
        );
    }

    public static function unhealthy(string $name): self
    {
        return new self(
            name: $name,
            isHealthy: false,
        );
    }

    public function name(): string
    {
        return $this->name;
    }

    public function isHealthy(): bool
    {
        return $this->isHealthy;
    }
}
