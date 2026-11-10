<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Shared\Health;

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Database\ConnectionResolverInterface;
use Infrastructure\Shared\Health\MySqlHealthCheck;
use Infrastructure\Shared\Health\RabbitMqHealthCheck;
use Infrastructure\Shared\Health\RedisHealthCheck;
use Infrastructure\Shared\Messaging\RabbitMqConnectionFactory;
use Psr\Log\NullLogger;
use Tests\Support\LaravelTestCase;

final class HealthChecksTest extends LaravelTestCase
{
    private const string UNREACHABLE_HOST = 'unreachable.invalid';

    public function test_MySqlCheckIsHealthyAgainstRealDatabase(): void
    {
        $check = $this->app->make(MySqlHealthCheck::class);

        self::assertSame('mysql', $check->name());
        self::assertTrue($check->isHealthy());
    }

    public function test_MySqlCheckIsUnhealthyWhenDatabaseIsUnreachable(): void
    {
        $this->app->make('config')
            ->set('database.connections.mysql.host', self::UNREACHABLE_HOST);
        $this->app->make('db')
            ->purge('mysql');
        $check = new MySqlHealthCheck(
            connections: $this->app->make(ConnectionResolverInterface::class),
            logger: new NullLogger(),
        );

        $isHealthy = $check->isHealthy();

        self::assertFalse($isHealthy);
    }

    public function test_RedisCheckIsHealthyAgainstRealRedis(): void
    {
        $check = $this->app->make(RedisHealthCheck::class);

        self::assertSame('redis', $check->name());
        self::assertTrue($check->isHealthy());
    }

    public function test_RedisCheckIsUnhealthyWhenRedisIsUnreachable(): void
    {
        $this->app->make('config')
            ->set('database.redis.default.host', self::UNREACHABLE_HOST);
        $this->app->make('redis')
            ->purge('default');
        $check = new RedisHealthCheck(redis: $this->app->make(RedisFactory::class), logger: new NullLogger());

        $isHealthy = $check->isHealthy();

        self::assertFalse($isHealthy);
    }

    public function test_RabbitMqCheckIsHealthyAgainstRealBroker(): void
    {
        $check = $this->app->make(RabbitMqHealthCheck::class);

        self::assertSame('rabbitmq', $check->name());
        self::assertTrue($check->isHealthy());
    }

    public function test_RabbitMqCheckIsUnhealthyWithWrongCredentials(): void
    {
        $configuredCredentials = $this->app->make(RabbitMqConnectionFactory::class);
        $wrongCredentials = new RabbitMqConnectionFactory(
            host: 'rabbitmq',
            port: 5672,
            user: 'nobody',
            password: 'wrong-password',
            vhost: '/',
            timeoutSeconds: 1.0,
        );

        $isHealthyWithConfiguredCredentials = new RabbitMqHealthCheck(
            connections: $configuredCredentials,
            logger: new NullLogger(),
        )->isHealthy();
        $isHealthyWithWrongCredentials = new RabbitMqHealthCheck(
            connections: $wrongCredentials,
            logger: new NullLogger(),
        )->isHealthy();

        self::assertTrue($isHealthyWithConfiguredCredentials);
        self::assertFalse($isHealthyWithWrongCredentials);
    }
}
