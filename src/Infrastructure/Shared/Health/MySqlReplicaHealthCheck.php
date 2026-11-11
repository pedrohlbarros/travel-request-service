<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Health;

use Application\Shared\Port\HealthCheck;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class MySqlReplicaHealthCheck implements HealthCheck
{
    public function __construct(
        private MySqlReplicaStatus $replica,
        private LoggerInterface $logger,
    ) {}

    public function name(): string
    {
        return 'mysql_replica';
    }

    public function isHealthy(): bool
    {
        try {
            $state = $this->replica->current();
        } catch (Throwable $exception) {
            $this->logger->warning('Readiness check failed', ['check' => $this->name(), 'exception' => $exception]);

            return false;
        }

        return $state?->isReplicating === true;
    }
}
