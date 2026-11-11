<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Shared\Health;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\DatabaseManager;
use Infrastructure\Shared\Health\MySqlReplicaHealthCheck;
use Infrastructure\Shared\Health\MySqlReplicaStatus;
use Tests\Support\LaravelTestCase;
use Tests\Support\Shared\Log\RecordingLogger;

/**
 * Against the replica of the compose environment, which make setup keeps replicating.
 */
final class MySqlReplicaHealthCheckTest extends LaravelTestCase
{
    private const string UNREACHABLE_HOST = 'no-such-replica.invalid';

    public function test_TheRunningReplicaIsHealthyAndCurrent(): void
    {
        $status = $this->app->make(MySqlReplicaStatus::class);

        $state = $status->current();
        $isHealthy = $this->healthCheck()
            ->isHealthy();
        $name = $this->healthCheck()
            ->name();

        self::assertNotNull($state);
        self::assertTrue($state->isReplicating);
        self::assertIsInt($state->secondsBehindSource);
        self::assertTrue($isHealthy);
        self::assertSame('mysql_replica', $name);
    }

    public function test_AServerThatIsNotAReplicaIsUnhealthy(): void
    {
        $this->pointReplicaConnectionTo(host: 'mysql');

        $state = $this->app->make(MySqlReplicaStatus::class)
            ->current();
        $isHealthy = $this->healthCheck()
            ->isHealthy();

        self::assertNull($state);
        self::assertFalse($isHealthy);
    }

    public function test_AnUnreachableReplicaIsUnhealthyAndLogged(): void
    {
        $this->pointReplicaConnectionTo(host: self::UNREACHABLE_HOST);
        $logger = new RecordingLogger();
        $replica = $this->app->make(MySqlReplicaStatus::class);
        $healthCheck = new MySqlReplicaHealthCheck(replica: $replica, logger: $logger);

        $isHealthy = $healthCheck->isHealthy();

        self::assertFalse($isHealthy);
        self::assertSame('Readiness check failed', $logger->records[0]['message'] ?? null);
    }

    private function healthCheck(): MySqlReplicaHealthCheck
    {
        return $this->app->make(MySqlReplicaHealthCheck::class);
    }

    private function pointReplicaConnectionTo(string $host): void
    {
        $this->app->make(Config::class)
            ->set('database.connections.' . MySqlReplicaStatus::CONNECTION . '.host', $host);
        $this->app->make(DatabaseManager::class)
            ->purge(MySqlReplicaStatus::CONNECTION);
    }
}
