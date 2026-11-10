<?php

declare(strict_types=1);

namespace Application\Shared\Health;

final readonly class ReadinessReport
{
    /**
     * @param array<string, bool> $checks check name => is healthy
     */
    public function __construct(
        public array $checks,
    ) {}

    public function isReady(): bool
    {
        return !\in_array(false, $this->checks, true);
    }
}
