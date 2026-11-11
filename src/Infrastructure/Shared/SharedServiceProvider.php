<?php

declare(strict_types=1);

namespace Infrastructure\Shared;

use Application\Shared\Port\Clock;
use Application\Shared\Port\DomainEventPublisher;
use Application\Shared\Port\IdentifierGenerator;
use Application\Shared\Port\Transaction;
use Application\Shared\UseCase\CheckReadinessUseCase;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Log\Context\Repository as Context;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Shared\Clock\SystemClock;
use Infrastructure\Shared\Config\TypedConfig;
use Infrastructure\Shared\Console\OutboxStatusCommand;
use Infrastructure\Shared\Console\RelayOutboxCommand;
use Infrastructure\Shared\Console\TapMessagesCommand;
use Infrastructure\Shared\Health\MySqlHealthCheck;
use Infrastructure\Shared\Health\RabbitMqHealthCheck;
use Infrastructure\Shared\Health\RedisHealthCheck;
use Infrastructure\Shared\Identifier\LaravelUuid7Generator;
use Infrastructure\Shared\Messaging\Outbox\EventMessageMapper;
use Infrastructure\Shared\Messaging\Outbox\OutboxDomainEventPublisher;
use Infrastructure\Shared\Messaging\Outbox\OutboxRelay;
use Infrastructure\Shared\Messaging\Outbox\OutboxRelaySettings;
use Infrastructure\Shared\Messaging\RabbitMq\RabbitMqOutboxPublisher;
use Infrastructure\Shared\Messaging\RabbitMqConnectionFactory;
use Infrastructure\Shared\Observability\ProcessMetrics;
use Infrastructure\Shared\Observability\Telemetry;
use Infrastructure\Shared\Persistence\DatabaseTransaction;
use Laravel\Octane\Events\RequestTerminated;

final class SharedServiceProvider extends ServiceProvider
{
    public const string HEALTH_CHECKS_TAG = 'health.checks';

    /** Each context tags its own event message mapper with this. */
    public const string EVENT_MESSAGE_MAPPERS_TAG = 'messaging.event_message_mappers';

    /** @var array<class-string, class-string> */
    public array $bindings = [
        Clock::class => SystemClock::class,
        IdentifierGenerator::class => LaravelUuid7Generator::class,
        Transaction::class => DatabaseTransaction::class,
    ];

    public function register(): void
    {
        $this->app->singleton(
            RabbitMqConnectionFactory::class,
            static function (Application $app): RabbitMqConnectionFactory {
                $config = $app->make(Config::class);

                return new RabbitMqConnectionFactory(
                    host: self::stringFrom(config: $config, key: 'rabbitmq.host'),
                    port: self::intFrom(config: $config, key: 'rabbitmq.port'),
                    user: self::stringFrom(config: $config, key: 'rabbitmq.user'),
                    password: self::stringFrom(config: $config, key: 'rabbitmq.password'),
                    vhost: self::stringFrom(config: $config, key: 'rabbitmq.vhost'),
                );
            },
        );

        $this->registerMessaging();

        $this->app->singleton(
            Telemetry::class,
            static function (Application $app): Telemetry {
                $config = $app->make(Config::class);
                $intervalMilliseconds = TypedConfig::positiveInt(
                    config: $config,
                    key: 'observability.metric_export_interval_ms',
                );

                return new Telemetry(metricExportIntervalMilliseconds: $intervalMilliseconds);
            },
        );

        $this->app->tag(
            [
                MySqlHealthCheck::class,
                RedisHealthCheck::class,
                RabbitMqHealthCheck::class,
            ],
            self::HEALTH_CHECKS_TAG,
        );

        $this->app->when(CheckReadinessUseCase::class)
            ->needs('$checks')
            ->giveTagged(self::HEALTH_CHECKS_TAG);
    }

    public function boot(Dispatcher $events, Telemetry $telemetry): void
    {
        ProcessMetrics::register(telemetry: $telemetry);
        $events->listen(RequestTerminated::class, static function () use ($telemetry): void {
            $telemetry->flush();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([RelayOutboxCommand::class, OutboxStatusCommand::class, TapMessagesCommand::class]);
        }
    }

    private function registerMessaging(): void
    {
        $this->app->bind(
            DomainEventPublisher::class,
            static function (Application $app): DomainEventPublisher {
                /** @var iterable<EventMessageMapper> $mappers */
                $mappers = $app->tagged(self::EVENT_MESSAGE_MAPPERS_TAG);
                $config = $app->make(Config::class);

                return new OutboxDomainEventPublisher(
                    connection: $app->make(ConnectionInterface::class),
                    mappers: $mappers,
                    identifiers: $app->make(IdentifierGenerator::class),
                    context: $app->make(Context::class),
                    telemetry: $app->make(Telemetry::class),
                    source: TypedConfig::nonEmptyString(config: $config, key: 'messaging.source'),
                );
            },
        );

        $this->app->singleton(
            OutboxRelaySettings::class,
            static function (Application $app): OutboxRelaySettings {
                $config = $app->make(Config::class);

                return new OutboxRelaySettings(
                    minPollMilliseconds: TypedConfig::positiveInt(config: $config, key: 'messaging.relay.min_poll_ms'),
                    maxPollMilliseconds: TypedConfig::positiveInt(config: $config, key: 'messaging.relay.max_poll_ms'),
                    batchSize: TypedConfig::positiveInt(config: $config, key: 'messaging.relay.batch_size'),
                    confirmTimeoutSeconds: TypedConfig::positiveInt(
                        config: $config,
                        key: 'messaging.relay.confirm_timeout_seconds',
                    ),
                );
            },
        );

        $this->app->bind(
            OutboxRelay::class,
            static function (Application $app): OutboxRelay {
                $settings = $app->make(OutboxRelaySettings::class);
                $publisher = new RabbitMqOutboxPublisher(
                    connections: $app->make(RabbitMqConnectionFactory::class),
                    confirmTimeoutSeconds: $settings->confirmTimeoutSeconds,
                );

                return new OutboxRelay(
                    connection: $app->make(ConnectionInterface::class),
                    publisher: $publisher,
                    clock: $app->make(Clock::class),
                    telemetry: $app->make(Telemetry::class),
                    batchSize: $settings->batchSize,
                );
            },
        );
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
