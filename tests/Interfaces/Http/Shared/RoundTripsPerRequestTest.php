<?php

declare(strict_types=1);

namespace Tests\Interfaces\Http\Shared;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Redis\Events\CommandExecuted;
use Illuminate\Redis\RedisManager;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\LaravelTestCase;
use Tests\Support\TravelRequest\CallsTravelRequestApi;

/**
 * Locks how many queries and Redis commands each route makes once the cache is warm, so a request that
 * starts doing more trips to MySQL or Redis fails here instead of only showing up under load.
 */
final class RoundTripsPerRequestTest extends LaravelTestCase
{
    use CallsTravelRequestApi;
    use RefreshDatabase;

    private const string REDIS_STORE = 'redis';

    private const string NOTIFICATIONS = '/api/v1/notifications';

    private int $queries = 0;

    private int $redisCommands = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(Config::class)
            ->set('cache.default', self::REDIS_STORE);
        $this->app->make(CacheManager::class)
            ->store(self::REDIS_STORE)
            ->clear();
        $redis = $this->app->make(RedisManager::class);
        $redis->enableEvents();
        $redis->purge('default');
        $redis->purge('cache');
        $events = $this->app->make(Dispatcher::class);
        $events->listen(QueryExecuted::class, function (): void {
            $this->queries++;
        });
        $events->listen(CommandExecuted::class, function (): void {
            $this->redisCommands++;
        });
        $this->signInEveryone();
    }

    public function test_ListingOwnTravelRequests(): void
    {
        $this->createTravelRequestAs(token: $this->requesterToken);

        $headers = $this->bearer(accessToken: $this->requesterToken);

        $listOwn = fn(): TestResponse => $this->getJson(self::TRAVEL_REQUESTS, $headers);

        $roundTrips = $this->roundTripsOfTheSecondCall(call: $listOwn);

        self::assertSame(['queries' => 0, 'redis' => 4], $roundTrips);
    }

    public function test_ShowingATravelRequest(): void
    {
        $created = $this->createTravelRequestAs(token: $this->requesterToken);
        $path = self::TRAVEL_REQUESTS . '/' . $this->idOf(travelRequest: $created);

        $headers = $this->bearer(accessToken: $this->requesterToken);

        $roundTrips = $this->roundTripsOfTheSecondCall(call: fn(): TestResponse => $this->getJson($path, $headers));

        self::assertSame(['queries' => 0, 'redis' => 3], $roundTrips);
    }

    public function test_ListingNotifications(): void
    {
        $headers = $this->bearer(accessToken: $this->requesterToken);

        $listNotifications = fn(): TestResponse => $this->getJson(self::NOTIFICATIONS, $headers);

        $roundTrips = $this->roundTripsOfTheSecondCall(call: $listNotifications);

        self::assertSame(['queries' => 1, 'redis' => 2], $roundTrips);
    }

    public function test_CreatingATravelRequest(): void
    {
        $this->createTravelRequestAs(token: $this->requesterToken);

        $roundTrips = $this->roundTripsOfTheSecondCall(call: fn(): array => $this->createTravelRequestAs(token: $this->requesterToken));

        self::assertSame(['queries' => 3, 'redis' => 7], $roundTrips);
    }

    public function test_ApprovingATravelRequest(): void
    {
        $first = $this->createTravelRequestAs(token: $this->requesterToken);
        $second = $this->createTravelRequestAs(token: $this->requesterToken);
        $this->writeAs(token: $this->adminToken, path: $this->approvePath(travelRequest: $first), ifMatch: '"1"');

        $roundTrips = $this->roundTripsOf(
            call: fn(): TestResponse => $this->writeAs(
                token: $this->adminToken,
                path: $this->approvePath(travelRequest: $second),
                ifMatch: '"1"',
            ),
        );

        self::assertSame(['queries' => 7, 'redis' => 7], $roundTrips);
    }

    /**
     * @param array<string, mixed> $travelRequest
     */
    private function approvePath(array $travelRequest): string
    {
        return self::TRAVEL_REQUESTS . '/' . $this->idOf(travelRequest: $travelRequest) . '/approve';
    }

    /**
     * @param callable(): (TestResponse<Response>|array<string, mixed>) $call
     *
     * @return array{queries: int, redis: int}
     */
    private function roundTripsOfTheSecondCall(callable $call): array
    {
        $call();

        return $this->roundTripsOf(call: $call);
    }

    /**
     * @param callable(): (TestResponse<Response>|array<string, mixed>) $call
     *
     * @return array{queries: int, redis: int}
     */
    private function roundTripsOf(callable $call): array
    {
        $this->queries = 0;
        $this->redisCommands = 0;
        $call();

        return ['queries' => $this->queries, 'redis' => $this->redisCommands];
    }
}
