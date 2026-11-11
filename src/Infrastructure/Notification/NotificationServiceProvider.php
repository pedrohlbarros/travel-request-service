<?php

declare(strict_types=1);

namespace Infrastructure\Notification;

use Application\Notification\Port\DecisionBroadcaster;
use Application\Notification\Port\DecisionNotifier;
use Application\Notification\Port\Notifications;
use Application\Notification\Port\ProcessedEvents;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Notification\Console\ConsumeDecisionsCommand;
use Infrastructure\Notification\Console\DeadLetterCommand;
use Infrastructure\Notification\Delivery\DecisionTextsFactory;
use Infrastructure\Notification\Delivery\LaravelDecisionNotifier;
use Infrastructure\Notification\Delivery\ReverbDecisionBroadcaster;
use Infrastructure\Notification\Messaging\DecisionConsumerSettings;
use Infrastructure\Notification\Persistence\DatabaseNotifications;
use Infrastructure\Notification\Persistence\DatabaseProcessedEvents;
use Infrastructure\Shared\Config\TypedConfig;

final class NotificationServiceProvider extends ServiceProvider
{
    private const float MILLISECONDS_PER_SECOND = 1000.0;

    /** @var array<class-string, class-string> */
    public array $bindings = [
        ProcessedEvents::class => DatabaseProcessedEvents::class,
        DecisionNotifier::class => LaravelDecisionNotifier::class,
        DecisionBroadcaster::class => ReverbDecisionBroadcaster::class,
        Notifications::class => DatabaseNotifications::class,
    ];

    public function register(): void
    {
        $this->app->bind(
            DecisionTextsFactory::class,
            static function (Application $app): DecisionTextsFactory {
                $locale = TypedConfig::nonEmptyString(config: $app->make(Config::class), key: 'notification.locale');

                return new DecisionTextsFactory(translator: $app->make(Translator::class), locale: $locale);
            },
        );

        $this->app->bind(
            DecisionConsumerSettings::class,
            static function (Application $app): DecisionConsumerSettings {
                $config = $app->make(Config::class);
                $waitMilliseconds = TypedConfig::positiveInt(config: $config, key: 'notification.consumer.wait_ms');

                return new DecisionConsumerSettings(
                    exchange: TypedConfig::nonEmptyString(config: $config, key: 'notification.consumer.exchange'),
                    queue: TypedConfig::nonEmptyString(config: $config, key: 'notification.consumer.queue'),
                    bindingKey: TypedConfig::nonEmptyString(config: $config, key: 'notification.consumer.binding_key'),
                    prefetch: TypedConfig::positiveInt(config: $config, key: 'notification.consumer.prefetch'),
                    retryDelaysMilliseconds: TypedConfig::positiveIntList(
                        config: $config,
                        key: 'notification.consumer.retry_delays_ms',
                    ),
                    confirmTimeoutSeconds: TypedConfig::positiveInt(
                        config: $config,
                        key: 'notification.consumer.confirm_timeout_seconds',
                    ),
                    waitSeconds: $waitMilliseconds / self::MILLISECONDS_PER_SECOND,
                );
            },
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ConsumeDecisionsCommand::class, DeadLetterCommand::class]);
        }
    }
}
