<?php

declare(strict_types=1);

namespace Tests\Support\Notification\Port;

use Application\Notification\Exception\NotificationNotFound;
use Application\Notification\Output\NotificationOutput;
use Application\Notification\Output\NotificationPage;
use Application\Notification\Port\NotificationListCriteria;
use Application\Notification\Port\Notifications;
use DateTimeImmutable;
use Domain\Shared\ValueObject\Uuid7;

/**
 * Notifications by recipient id; the cursor is not interpreted, only passed along.
 */
final class InMemoryNotifications implements Notifications
{
    /** @var array<string, array<string, NotificationOutput>> */
    private array $byRecipient = [];

    public private(set) ?string $lastCursor = null;

    public function store(string $recipientId, NotificationOutput $notification): void
    {
        $this->byRecipient[$recipientId][$notification->id] = $notification;
    }

    public function list(NotificationListCriteria $criteria): NotificationPage
    {
        $this->lastCursor = $criteria->cursor;
        $owned = array_values($this->byRecipient[$criteria->recipientId->value] ?? []);
        $listed = array_values(array_filter(
            $owned,
            static fn(NotificationOutput $notification): bool => ! $criteria->isUnreadOnly || $notification->readAt === null,
        ));

        return new NotificationPage(
            notifications: \array_slice($listed, 0, $criteria->perPage),
            perPage: $criteria->perPage,
            nextCursor: null,
            previousCursor: null,
        );
    }

    public function markRead(Uuid7 $recipientId, Uuid7 $notificationId, DateTimeImmutable $readAt): NotificationOutput
    {
        $notification = $this->byRecipient[$recipientId->value][$notificationId->value] ?? null;

        if ($notification === null) {
            throw NotificationNotFound::withId(id: $notificationId->value);
        }

        $read = new NotificationOutput(
            id: $notification->id,
            type: $notification->type,
            message: $notification->message,
            details: $notification->details,
            readAt: $notification->readAt ?? $readAt,
            createdAt: $notification->createdAt,
        );
        $this->byRecipient[$recipientId->value][$notificationId->value] = $read;

        return $read;
    }
}
