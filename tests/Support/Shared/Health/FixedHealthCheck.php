<?php

declare(strict_types=1);

namespace Tests\Support\Shared\Health;

use Application\Shared\Health\HealthCheck;

final readonly class FixedHealthCheck implements HealthCheck
{
    public function __construct(
        private string $name,
        private bool $isHealthy,
    ) {}

    public static function healthy(string $name): self
    {
        return new self($name, true);
    }

    public static function unhealthy(string $name): self
    {
        return new self($name, false);
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
