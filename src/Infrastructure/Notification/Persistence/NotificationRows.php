<?php

declare(strict_types=1);

namespace Infrastructure\Notification\Persistence;

use Application\Notification\Output\NotificationOutput;
use DateTimeImmutable;
use DateTimeZone;
use Infrastructure\Shared\Persistence\BinaryUuid;
use stdClass;

final readonly class NotificationRows
{
    public const string TABLE = 'notifications';

    public const string DATETIME_FORMAT = 'Y-m-d H:i:s';

    public const string TIMEZONE = 'UTC';

    /**
     * The rows are written by the notifications channel, so their shape is known; the asserts only narrow types.
     */
    public static function toOutput(stdClass $row): NotificationOutput
    {
        \assert(\is_string($row->id) && \is_string($row->type) && \is_string($row->message));
        \assert(\is_string($row->data) && \is_string($row->created_at));
        \assert($row->read_at === null || \is_string($row->read_at));
        $details = json_decode($row->data, true, flags: JSON_THROW_ON_ERROR);
        \assert(\is_array($details));
        $utc = new DateTimeZone(self::TIMEZONE);
        $readAt = $row->read_at === null ? null : new DateTimeImmutable($row->read_at, $utc);
        $notificationId = BinaryUuid::fromBytes(bytes: $row->id);

        /** @var array<string, mixed> $details */
        return new NotificationOutput(
            id: $notificationId,
            type: $row->type,
            message: $row->message,
            details: $details,
            readAt: $readAt,
            createdAt: new DateTimeImmutable($row->created_at, $utc),
        );
    }
}
