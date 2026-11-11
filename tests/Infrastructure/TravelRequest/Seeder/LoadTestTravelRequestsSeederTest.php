<?php

declare(strict_types=1);

namespace Tests\Infrastructure\TravelRequest\Seeder;

use DateTimeImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Identity\Seeder\LoadTestUsersSeeder;
use Infrastructure\TravelRequest\Seeder\LoadTestTravelRequestsSeeder;
use RuntimeException;
use Tests\Support\LaravelTestCase;

final class LoadTestTravelRequestsSeederTest extends LaravelTestCase
{
    use RefreshDatabase;

    private const int TRAVEL_REQUESTS = 600;

    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->app->make(Config::class);
        $config->set('load_test.users', 2);
        $config->set('load_test.admins', 1);
        $config->set('load_test.travel_requests', self::TRAVEL_REQUESTS);
    }

    public function test_SpreadsTheRequestsOverTheLoadUsersWithEveryStatus(): void
    {
        $this->seed(LoadTestUsersSeeder::class);

        $this->seed(LoadTestTravelRequestsSeeder::class);

        $rows = $this->travelRequestRows();
        $distinctStatuses = array_unique(array_column($rows, 'status'));
        $statuses = array_values($distinctStatuses);
        sort($statuses);
        $requesters = array_unique(array_column($rows, 'requester_id'));
        $inconsistentRows = array_filter($rows, static fn(array $row): bool => ! self::isConsistent(row: $row));

        self::assertCount(self::TRAVEL_REQUESTS, $rows);
        self::assertSame(['approved', 'cancelled', 'requested'], $statuses);
        self::assertCount(2, $requesters);
        self::assertSame([], $inconsistentRows);
    }

    public function test_RunningAgainOnlyTopsUpToTheTarget(): void
    {
        $this->seed(LoadTestUsersSeeder::class);
        $this->seed(LoadTestTravelRequestsSeeder::class);

        $this->seed(LoadTestTravelRequestsSeeder::class);

        self::assertCount(self::TRAVEL_REQUESTS, $this->travelRequestRows());
    }

    public function test_NeedsTheLoadUsersFirst(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Seed the load test users first.');

        $this->seed(LoadTestTravelRequestsSeeder::class);
    }

    public function test_RefusesToRunInProduction(): void
    {
        $this->app->detectEnvironment(static fn(): string => 'production');

        $this->expectExceptionMessageIs('Load test travel requests are never seeded in production.');

        $this->app->make(LoadTestTravelRequestsSeeder::class)
            ->setContainer($this->app)
            ->__invoke();
    }

    /**
     * Decided requests are at version 2 with a decider; every trip is ahead and returns after it departs.
     *
     * @param array{status: string, requester_id: string, version: int, decided_by: ?string, departure_date: string, return_date: string} $row
     */
    private static function isConsistent(array $row): bool
    {
        $isDecided = $row['status'] !== 'requested';
        $departure = new DateTimeImmutable($row['departure_date']);

        return $row['version'] === ($isDecided ? 2 : 1)
            && ($row['decided_by'] !== null) === $isDecided
            && $departure > new DateTimeImmutable('today')
            && $row['return_date'] >= $row['departure_date'];
    }

    /**
     * @return list<array{status: string, requester_id: string, version: int, decided_by: ?string, departure_date: string, return_date: string}>
     */
    private function travelRequestRows(): array
    {
        $rows = [];
        $storedRows = $this->app->make(ConnectionInterface::class)
            ->table('travel_requests')
            ->get();

        foreach ($storedRows as $storedRow) {
            \assert(\is_string($storedRow->status) && \is_string($storedRow->requester_id));
            \assert(\is_string($storedRow->departure_date) && \is_string($storedRow->return_date));
            \assert(\is_numeric($storedRow->version));
            \assert($storedRow->decided_by === null || \is_string($storedRow->decided_by));
            $rows[] = [
                'status' => $storedRow->status,
                'requester_id' => $storedRow->requester_id,
                'version' => (int) $storedRow->version,
                'decided_by' => $storedRow->decided_by,
                'departure_date' => $storedRow->departure_date,
                'return_date' => $storedRow->return_date,
            ];
        }

        return $rows;
    }
}
