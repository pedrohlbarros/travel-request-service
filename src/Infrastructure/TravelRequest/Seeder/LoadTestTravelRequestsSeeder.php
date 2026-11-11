<?php

declare(strict_types=1);

namespace Infrastructure\TravelRequest\Seeder;

use Application\Shared\Port\Clock;
use Application\Shared\Port\IdentifierGenerator;
use DateTimeImmutable;
use Domain\TravelRequest\Entity\TravelRequest;
use Domain\TravelRequest\Enum\TravelRequestStatus;
use Domain\TravelRequest\ValueObject\Destination;
use Domain\TravelRequest\ValueObject\TravelPeriod;
use Domain\TravelRequest\ValueObject\TravelRequestId;
use Domain\TravelRequest\ValueObject\UserId;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Seeder;
use Infrastructure\Shared\Config\TypedConfig;
use Infrastructure\Shared\Persistence\BinaryUuid;
use Infrastructure\TravelRequest\Persistence\TravelRequestRows;
use RuntimeException;

/**
 * Spreads travel requests over the load test users, so lists and filters have real pages to go through: most
 * still requested and in the future, the rest already decided by a load admin.
 */
final class LoadTestTravelRequestsSeeder extends Seeder
{
    public const string USER_EMAIL_PATTERN = 'load-user-%@example.com';

    public const string ADMIN_EMAIL_PATTERN = 'load-admin-%@example.com';

    public const array DESTINATIONS = ['Lisbon', 'Tokyo', 'Paris', 'New York', 'Buenos Aires', 'Cape Town', 'Berlin', 'Lima'];

    private const int CHUNK_SIZE = 500;

    private const int PERCENT = 100;

    private const int APPROVED_PERCENT = 25;

    private const int CANCELLED_PERCENT = 15;

    private const int LONGEST_TRIP_DAYS = 14;

    private const int FURTHEST_DEPARTURE_DAYS = 180;

    private const int OLDEST_REQUEST_DAYS = 90;

    private const int MINUTES_PER_DAY = 1440;

    public function run(
        Application $app,
        Config $config,
        ConnectionInterface $connection,
        IdentifierGenerator $identifiers,
        Clock $clock,
    ): void {
        if ($app->environment('production')) {
            throw new RuntimeException('Load test travel requests are never seeded in production.');
        }

        $requesterIds = $this->userIds(connection: $connection, emailPattern: self::USER_EMAIL_PATTERN);
        $adminIds = $this->userIds(connection: $connection, emailPattern: self::ADMIN_EMAIL_PATTERN);

        if ($requesterIds === [] || $adminIds === []) {
            throw new RuntimeException('Seed the load test users first.');
        }

        $target = TypedConfig::positiveInt(config: $config, key: 'load_test.travel_requests');
        $missing = $target - $this->existingCount(connection: $connection, requesterIds: $requesterIds);
        $rows = [];

        for ($created = 0; $created < $missing; $created++) {
            $travelRequest = $this->travelRequest(
                id: $identifiers->uuid7(),
                requesterId: $requesterIds[array_rand($requesterIds)],
                adminId: $adminIds[array_rand($adminIds)],
                today: $clock->now(),
            );
            $idBytes = BinaryUuid::toBytes(uuid: $travelRequest->id->toString());
            $rows[] = [...TravelRequestRows::columnsOf(travelRequest: $travelRequest), 'id' => $idBytes];

            if (\count($rows) === self::CHUNK_SIZE) {
                self::insert(connection: $connection, rows: $rows);
                $rows = [];
            }
        }

        self::insert(connection: $connection, rows: $rows);
        $createdCount = max(0, $missing);
        $this->command?->line(\sprintf('  %d load test travel requests created', $createdCount));
    }

    private function travelRequest(string $id, string $requesterId, string $adminId, DateTimeImmutable $today): TravelRequest
    {
        $roll = random_int(1, self::PERCENT);
        $status = match (true) {
            $roll <= self::APPROVED_PERCENT => TravelRequestStatus::Approved,
            $roll <= self::APPROVED_PERCENT + self::CANCELLED_PERCENT => TravelRequestStatus::Cancelled,
            default => TravelRequestStatus::Requested,
        };
        $departureOffset = \sprintf('+%d days', random_int(1, self::FURTHEST_DEPARTURE_DAYS));
        $tripOffset = \sprintf('+%d days', random_int(0, self::LONGEST_TRIP_DAYS));
        $ageOffset = \sprintf('-%d minutes', random_int(0, self::OLDEST_REQUEST_DAYS * self::MINUTES_PER_DAY));
        $departure = $today->modify($departureOffset)
            ->setTime(0, 0);
        $return = $departure->modify($tripOffset);
        $createdAt = $today->modify($ageOffset);
        $isDecided = $status !== TravelRequestStatus::Requested;
        $destination = self::DESTINATIONS[array_rand(self::DESTINATIONS)];
        $period = TravelPeriod::between(departureDate: $departure, returnDate: $return);

        return TravelRequest::reconstitute(
            id: TravelRequestId::fromString(id: $id),
            requesterId: UserId::fromString(id: $requesterId),
            destination: Destination::fromString(name: $destination),
            period: $period,
            status: $status,
            version: $isDecided ? TravelRequest::FIRST_VERSION + 1 : TravelRequest::FIRST_VERSION,
            createdAt: $createdAt,
            updatedAt: $createdAt,
            decidedBy: $isDecided ? UserId::fromString(id: $adminId) : null,
        );
    }

    /**
     * @return list<string>
     */
    private function userIds(ConnectionInterface $connection, string $emailPattern): array
    {
        $ids = [];

        $idsBytes = $connection->table('users')
            ->where('email', 'like', $emailPattern)
            ->pluck('id');

        foreach ($idsBytes as $idBytes) {
            \assert(\is_string($idBytes));
            $ids[] = BinaryUuid::fromBytes(bytes: $idBytes);
        }

        return $ids;
    }

    /**
     * @param list<string> $requesterIds
     */
    private function existingCount(ConnectionInterface $connection, array $requesterIds): int
    {
        $requesterIdBytes = array_map(static fn(string $id): string => BinaryUuid::toBytes(uuid: $id), $requesterIds);

        return $connection->table('travel_requests')
            ->whereIn('requester_id', $requesterIdBytes)
            ->count();
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private static function insert(ConnectionInterface $connection, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $connection->table('travel_requests')
            ->insert($rows);
    }
}
