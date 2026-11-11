<?php

declare(strict_types=1);

namespace Infrastructure\TravelRequest;

use Application\TravelRequest\Port\TravelRequestQueries;
use Domain\TravelRequest\Repository\TravelRequestRepository;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Shared\Config\TypedConfig;
use Infrastructure\Shared\Observability\Telemetry;
use Infrastructure\Shared\SharedServiceProvider;
use Infrastructure\TravelRequest\Cache\CachedTravelRequestQueries;
use Infrastructure\TravelRequest\Cache\CacheInvalidatingTravelRequestRepository;
use Infrastructure\TravelRequest\Cache\TravelRequestCacheSettings;
use Infrastructure\TravelRequest\Messaging\TravelRequestEventMessages;
use Infrastructure\TravelRequest\Observability\MetricsRecordingTravelRequestRepository;
use Infrastructure\TravelRequest\Persistence\EloquentTravelRequestRepository;
use Infrastructure\TravelRequest\Query\DatabaseTravelRequestQueries;

final class TravelRequestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            TravelRequestCacheSettings::class,
            static function (Application $app): TravelRequestCacheSettings {
                $config = $app->make(Config::class);

                return new TravelRequestCacheSettings(
                    detailSeconds: TypedConfig::positiveInt(config: $config, key: 'travel_request.cache.detail_ttl'),
                    listSeconds: TypedConfig::positiveInt(config: $config, key: 'travel_request.cache.list_ttl'),
                );
            },
        );

        $this->app->bind(
            TravelRequestRepository::class,
            static function (Application $app): TravelRequestRepository {
                $transactions = $app->make('db.transactions');
                $cacheInvalidating = new CacheInvalidatingTravelRequestRepository(
                    travelRequests: new EloquentTravelRequestRepository(),
                    cache: $app->make(Cache::class),
                    transactions: $transactions,
                );

                return new MetricsRecordingTravelRequestRepository(
                    travelRequests: $cacheInvalidating,
                    telemetry: $app->make(Telemetry::class),
                    transactions: $transactions,
                );
            },
        );

        $this->app->bind(
            TravelRequestEventMessages::class,
            static function (Application $app): TravelRequestEventMessages {
                $config = $app->make(Config::class);
                $exchange = TypedConfig::nonEmptyString(config: $config, key: 'travel_request.events.exchange');

                return new TravelRequestEventMessages(exchange: $exchange);
            },
        );
        $this->app->tag([TravelRequestEventMessages::class], SharedServiceProvider::EVENT_MESSAGE_MAPPERS_TAG);

        $this->app->bind(
            TravelRequestQueries::class,
            static function (Application $app): TravelRequestQueries {
                $primary = $app->make(ConnectionInterface::class);

                return new CachedTravelRequestQueries(
                    queries: new DatabaseTravelRequestQueries(),
                    cache: $app->make(Cache::class),
                    settings: $app->make(TravelRequestCacheSettings::class),
                    primary: $primary,
                );
            },
        );
    }
}
