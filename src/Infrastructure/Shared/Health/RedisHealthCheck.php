<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Health;

use Application\Shared\Health\HealthCheck;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class RedisHealthCheck implements HealthCheck
{
    public function __construct(
        private RedisFactory $redis,
        private LoggerInterface $logger,
    ) {}

    public function name(): string
    {
        return 'redis';
    }

    public function isHealthy(): bool
    {
        try {
            $this->redis->connection()->command('ping');

            return true;
        } catch (Throwable $exception) {
            $this->logger->warning('Readiness check failed', ['check' => $this->name(), 'exception' => $exception]);

            return false;
        }
    }
}
