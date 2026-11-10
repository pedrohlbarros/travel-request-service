<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Health;

use Application\Shared\Port\HealthCheck;
use Illuminate\Database\ConnectionResolverInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class MySqlHealthCheck implements HealthCheck
{
    public function __construct(
        private ConnectionResolverInterface $connections,
        private LoggerInterface $logger,
    ) {}

    public function name(): string
    {
        return 'mysql';
    }

    public function isHealthy(): bool
    {
        try {
            $this->connections->connection()->select('SELECT 1');

            return true;
        } catch (Throwable $exception) {
            $this->logger->warning('Readiness check failed', ['check' => $this->name(), 'exception' => $exception]);

            return false;
        }
    }
}
