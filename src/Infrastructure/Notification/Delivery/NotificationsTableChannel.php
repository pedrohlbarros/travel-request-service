<?php

declare(strict_types=1);

namespace Infrastructure\Notification\Delivery;

use Application\Shared\Port\Clock;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Infrastructure\Notification\Persistence\NotificationRows;
use Infrastructure\Shared\Persistence\BinaryUuid;

/**
 * The database channel for our own table, keyed by BINARY(16) ids instead of Laravel's morph columns.
 */
final readonly class NotificationsTableChannel
{
    private const int JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function send(NotifiableRecipient $notifiable, TravelRequestDecidedNotification $notification): void
    {
        $decision = $notification->decision;
        $createdAt = $this->clock->now()
            ->setTimezone(new DateTimeZone(NotificationRows::TIMEZONE));
        $details = json_encode(DecisionDetails::of(decision: $decision), self::JSON_FLAGS);

        $this->connection->table(NotificationRows::TABLE)
            ->insert([
                'id' => BinaryUuid::toBytes(uuid: $decision->eventId->value),
                'notifiable_id' => BinaryUuid::toBytes(uuid: $notifiable->recipient->id->value),
                'type' => DecisionDetails::typeOf(decision: $decision),
                'message' => $notification->texts->message,
                'data' => $details,
                'read_at' => null,
                'created_at' => $createdAt->format(NotificationRows::DATETIME_FORMAT),
            ]);
    }
}
