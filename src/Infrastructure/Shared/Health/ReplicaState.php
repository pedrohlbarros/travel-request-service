<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Health;

final readonly class ReplicaState
{
    public function __construct(
        public bool $isReplicating,
        public ?int $secondsBehindSource,
    ) {}
}
