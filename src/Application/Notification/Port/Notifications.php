<?php

declare(strict_types=1);

namespace Application\Notification\Port;

use Application\Notification\Exception\NotificationNotFound;
use Application\Notification\Output\NotificationOutput;
use Application\Notification\Output\NotificationPage;
use Application\Shared\Exception\InvalidListCursor;
use DateTimeImmutable;
use Domain\Shared\ValueObject\Uuid7;

interface Notifications
{
    /**
     * Newest first, only the recipient's own.
     *
     * @throws InvalidListCursor
     */
    public function list(NotificationListCriteria $criteria): NotificationPage;

    /**
     * Keeps the first read time when the notification was already read.
     *
     * @throws NotificationNotFound when it does not exist or belongs to someone else
     */
    public function markRead(Uuid7 $recipientId, Uuid7 $notificationId, DateTimeImmutable $readAt): NotificationOutput;
}
