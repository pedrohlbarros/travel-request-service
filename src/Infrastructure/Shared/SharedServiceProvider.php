<?php

declare(strict_types=1);

namespace Infrastructure\Shared;

use Application\Shared\Health\CheckReadinessHandler;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Shared\Health\MySqlHealthCheck;
use Infrastructure\Shared\Health\RabbitMqHealthCheck;
use Infrastructure\Shared\Health\RedisHealthCheck;
use Infrastructure\Shared\Messaging\RabbitMqConnectionFactory;

final class SharedServiceProvider extends ServiceProvider
{
    public const string HEALTH_CHECKS_TAG = 'health.checks';

    public function register(): void
    {
        $this->app->singleton(RabbitMqConnectionFactory::class, static function (Application $app): RabbitMqConnectionFactory {
            $config = $app->make(Config::class);

            return new RabbitMqConnectionFactory(
                host: self::stringFrom($config, 'rabbitmq.host'),
                port: self::intFrom($config, 'rabbitmq.port'),
                user: self::stringFrom($config, 'rabbitmq.user'),
                password: self::stringFrom($config, 'rabbitmq.password'),
                vhost: self::stringFrom($config, 'rabbitmq.vhost'),
            );
        });

        $this->app->tag([
            MySqlHealthCheck::class,
            RedisHealthCheck::class,
            RabbitMqHealthCheck::class,
        ], self::HEALTH_CHECKS_TAG);

        $this->app->when(CheckReadinessHandler::class)
            ->needs('$checks')
            ->giveTagged(self::HEALTH_CHECKS_TAG);
    }

    private static function stringFrom(Config $config, string $key): string
    {
        $value = $config->get($key);

        return \is_scalar($value) ? (string) $value : '';
    }

    private static function intFrom(Config $config, string $key): int
    {
        $value = $config->get($key);

        return is_numeric($value) ? (int) $value : 0;
    }
}
