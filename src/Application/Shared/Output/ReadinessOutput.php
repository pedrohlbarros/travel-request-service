<?php

declare(strict_types=1);

namespace Application\Shared\Output;

final readonly class ReadinessOutput
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
