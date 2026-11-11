<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Messaging\Outbox;

/**
 * Polls fast while there is work and backs off, doubling up to a ceiling, while there is none or the broker fails.
 */
final readonly class PollDelay
{
    private const int BACKOFF_FACTOR = 2;

    private function __construct(
        public int $milliseconds,
        private int $minMilliseconds,
        private int $maxMilliseconds,
    ) {}

    public static function startingAt(int $minMilliseconds, int $maxMilliseconds): self
    {
        return new self(
            milliseconds: $minMilliseconds,
            minMilliseconds: $minMilliseconds,
            maxMilliseconds: $maxMilliseconds,
        );
    }

    public function afterBatch(int $publishedMessages): self
    {
        if ($publishedMessages > 0) {
            return self::startingAt(minMilliseconds: $this->minMilliseconds, maxMilliseconds: $this->maxMilliseconds);
        }

        return $this->backedOff();
    }

    public function backedOff(): self
    {
        return new self(
            milliseconds: min($this->milliseconds * self::BACKOFF_FACTOR, $this->maxMilliseconds),
            minMilliseconds: $this->minMilliseconds,
            maxMilliseconds: $this->maxMilliseconds,
        );
    }
}
