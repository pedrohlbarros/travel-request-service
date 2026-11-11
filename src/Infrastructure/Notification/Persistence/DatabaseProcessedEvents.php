<?php

declare(strict_types=1);

namespace Infrastructure\Notification\Persistence;

use Application\Notification\Port\ProcessedEvents;
use Application\Shared\Port\Clock;
use DateTimeZone;
use Domain\Shared\ValueObject\Uuid7;
use Illuminate\Database\ConnectionInterface;
use Infrastructure\Shared\Persistence\BinaryUuid;

final readonly class DatabaseProcessedEvents implements ProcessedEvents
{
    public const string TABLE = 'processed_events';

    public const string CONSUMER = 'notification.travel_request';

    private const string DATETIME_FORMAT = 'Y-m-d H:i:s';

    private const string TIMEZONE = 'UTC';

    private const int ROWS_WRITTEN_THE_FIRST_TIME = 1;

    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function recordFirstDelivery(Uuid7 $eventId): bool
    {
        $processedAt = $this->clock->now()
            ->setTimezone(new DateTimeZone(self::TIMEZONE));

        $insertedRows = $this->connection->table(self::TABLE)
            ->insertOrIgnore([
                'consumer' => self::CONSUMER,
                'event_id' => BinaryUuid::toBytes(uuid: $eventId->value),
                'processed_at' => $processedAt->format(self::DATETIME_FORMAT),
            ]);

        return $insertedRows === self::ROWS_WRITTEN_THE_FIRST_TIME;
    }
}
