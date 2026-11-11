<?php

declare(strict_types=1);

namespace Infrastructure\TravelRequest;

use Application\TravelRequest\Port\TravelRequestQueries;
use Domain\TravelRequest\Repository\TravelRequestRepository;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Shared\Config\TypedConfig;
use Infrastructure\TravelRequest\Cache\CachedTravelRequestQueries;
use Infrastructure\TravelRequest\Cache\CacheInvalidatingTravelRequestRepository;
use Infrastructure\TravelRequest\Cache\TravelRequestCacheSettings;
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
            static fn(Application $app): TravelRequestRepository => new CacheInvalidatingTravelRequestRepository(
                travelRequests: new EloquentTravelRequestRepository(),
                cache: $app->make(Cache::class),
            ),
        );

        $this->app->bind(
            TravelRequestQueries::class,
            static fn(Application $app): TravelRequestQueries => new CachedTravelRequestQueries(
                queries: new DatabaseTravelRequestQueries(),
                cache: $app->make(Cache::class),
                settings: $app->make(TravelRequestCacheSettings::class),
            ),
        );
    }
}
