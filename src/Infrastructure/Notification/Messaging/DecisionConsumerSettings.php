<?php

declare(strict_types=1);

namespace Infrastructure\Notification\Messaging;

final readonly class DecisionConsumerSettings
{
    /**
     * @param non-empty-list<positive-int> $retryDelaysMilliseconds one wait queue per retry, in order
     */
    public function __construct(
        public string $exchange,
        public string $queue,
        public string $bindingKey,
        public int $prefetch,
        public array $retryDelaysMilliseconds,
        public int $confirmTimeoutSeconds,
        public float $waitSeconds,
    ) {}

    public function deadLetterQueue(): string
    {
        return $this->queue . '.dlq';
    }

    public function retryQueue(int $retry): string
    {
        $delayMilliseconds = $this->retryDelaysMilliseconds[$retry - 1];

        return $this->queue . '.retry.' . self::readable(milliseconds: $delayMilliseconds);
    }

    public function retryDelay(int $retry): int
    {
        return $this->retryDelaysMilliseconds[$retry - 1];
    }

    public function maxRetries(): int
    {
        return \count($this->retryDelaysMilliseconds);
    }

    private static function readable(int $milliseconds): string
    {
        if ($milliseconds % 60_000 === 0) {
            return ($milliseconds / 60_000) . 'm';
        }

        if ($milliseconds % 1000 === 0) {
            return ($milliseconds / 1000) . 's';
        }

        return $milliseconds . 'ms';
    }
}
