<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Messaging\Outbox;

final readonly class OutboxRelaySettings
{
    public function __construct(
        public int $minPollMilliseconds,
        public int $maxPollMilliseconds,
        public int $batchSize,
        public int $confirmTimeoutSeconds,
    ) {}
}
