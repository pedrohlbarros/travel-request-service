<?php

declare(strict_types=1);

namespace Infrastructure\Notification\Persistence;

use Application\Notification\Exception\NotificationNotFound;
use Application\Notification\Output\NotificationOutput;
use Application\Notification\Output\NotificationPage;
use Application\Notification\Port\NotificationListCriteria;
use Application\Notification\Port\Notifications;
use DateTimeImmutable;
use DateTimeZone;
use Domain\Shared\ValueObject\Uuid7;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Infrastructure\Shared\Persistence\BinaryUuid;
use Infrastructure\Shared\Query\ListCursor;
use Infrastructure\Shared\Query\ListDirection;
use stdClass;

final readonly class DatabaseNotifications implements Notifications
{
    private const int ONE_EXTRA_ROW = 1;

    private const array COLUMNS = ['id', 'type', 'message', 'data', 'read_at', 'created_at'];

    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    /**
     * Keyset pagination on id, which is time-ordered: one extra row tells whether there is another page.
     */
    public function list(NotificationListCriteria $criteria): NotificationPage
    {
        $cursor = $criteria->cursor === null ? null : ListCursor::decode(encodedCursor: $criteria->cursor);
        $isGoingNewer = $cursor?->direction === ListDirection::Newer;
        $query = $this->filtered(criteria: $criteria, cursor: $cursor);

        $rows = $query->orderBy('id', $isGoingNewer ? 'asc' : 'desc')
            ->limit($criteria->perPage + self::ONE_EXTRA_ROW)
            ->get(self::COLUMNS);
        $hasMoreInThatDirection = $rows->count() > $criteria->perPage;
        $pageRows = $rows->take($criteria->perPage)
            ->all();
        $newestFirstRows = $isGoingNewer ? array_reverse($pageRows) : $pageRows;
        $notifications = [];

        foreach ($newestFirstRows as $row) {
            $notifications[] = NotificationRows::toOutput(row: $row);
        }

        return $this->page(
            notifications: $notifications,
            criteria: $criteria,
            hasOlder: $isGoingNewer || $hasMoreInThatDirection,
            hasNewer: $isGoingNewer ? $hasMoreInThatDirection : $cursor !== null,
        );
    }

    public function markRead(Uuid7 $recipientId, Uuid7 $notificationId, DateTimeImmutable $readAt): NotificationOutput
    {
        $notificationIdBytes = BinaryUuid::toBytes(uuid: $notificationId->value);
        $readAtUtc = $readAt->setTimezone(new DateTimeZone(NotificationRows::TIMEZONE));

        $this->ownedBy(recipientId: $recipientId)
            ->where('id', $notificationIdBytes)
            ->whereNull('read_at')
            ->update(['read_at' => $readAtUtc->format(NotificationRows::DATETIME_FORMAT)]);

        $row = $this->ownedBy(recipientId: $recipientId)
            ->where('id', $notificationIdBytes)
            ->first(self::COLUMNS);

        if (! $row instanceof stdClass) {
            throw NotificationNotFound::withId(id: $notificationId->value);
        }

        return NotificationRows::toOutput(row: $row);
    }

    private function filtered(NotificationListCriteria $criteria, ?ListCursor $cursor): QueryBuilder
    {
        $query = $this->ownedBy(recipientId: $criteria->recipientId);

        if ($criteria->isUnreadOnly) {
            $query->whereNull('read_at');
        }

        if ($cursor !== null) {
            $isGoingNewer = $cursor->direction === ListDirection::Newer;
            $edgeIdBytes = BinaryUuid::toBytes(uuid: $cursor->edgeId->value);
            $query->where('id', $isGoingNewer ? '>' : '<', $edgeIdBytes);
        }

        return $query;
    }

    private function ownedBy(Uuid7 $recipientId): QueryBuilder
    {
        $recipientIdBytes = BinaryUuid::toBytes(uuid: $recipientId->value);

        return $this->connection->table(NotificationRows::TABLE)
            ->where('notifiable_id', $recipientIdBytes);
    }

    /**
     * @param list<NotificationOutput> $notifications newest first
     */
    private function page(
        array $notifications,
        NotificationListCriteria $criteria,
        bool $hasOlder,
        bool $hasNewer,
    ): NotificationPage {
        $oldest = $notifications[array_key_last($notifications) ?? 0] ?? null;
        $newest = $notifications[0] ?? null;
        $nextCursor = $hasOlder && $oldest !== null ? ListCursor::olderThan(edgeId: $oldest->id) : null;
        $previousCursor = $hasNewer && $newest !== null ? ListCursor::newerThan(edgeId: $newest->id) : null;

        return new NotificationPage(
            notifications: $notifications,
            perPage: $criteria->perPage,
            nextCursor: $nextCursor?->encode(),
            previousCursor: $previousCursor?->encode(),
        );
    }
}
