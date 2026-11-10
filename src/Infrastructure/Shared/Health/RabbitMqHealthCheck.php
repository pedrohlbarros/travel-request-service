<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Health;

use Application\Shared\Port\HealthCheck;
use Infrastructure\Shared\Messaging\RabbitMqConnectionFactory;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class RabbitMqHealthCheck implements HealthCheck
{
    public function __construct(
        private RabbitMqConnectionFactory $connections,
        private LoggerInterface $logger,
    ) {}

    public function name(): string
    {
        return 'rabbitmq';
    }

    public function isHealthy(): bool
    {
        try {
            $connection = $this->connections->create();
            $isConnected = $connection->isConnected();
            $connection->close();

            return $isConnected;
        } catch (Throwable $exception) {
            $this->logger->warning('Readiness check failed', ['check' => $this->name(), 'exception' => $exception]);

            return false;
        }
    }
}
