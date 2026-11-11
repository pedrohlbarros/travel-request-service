<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Notification\Persistence;

use Application\Notification\Exception\NotificationNotFound;
use Application\Notification\Output\NotificationOutput;
use Application\Notification\Output\NotificationPage;
use Application\Notification\Port\NotificationListCriteria;
use Application\Shared\Exception\InvalidListCursor;
use DateTimeImmutable;
use Domain\Shared\ValueObject\Uuid7;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Infrastructure\Notification\Persistence\DatabaseNotifications;
use Tests\Support\LaravelTestCase;
use Tests\Support\Notification\Fixture\DecisionMother as Mother;
use Tests\Support\Notification\Fixture\StoresNotifications;

final class DatabaseNotificationsTest extends LaravelTestCase
{
    use RefreshDatabase;
    use StoresNotifications;

    private const string FIRST_READ_AT = '2026-11-10 10:00:00';

    private const string LATER_READ_AT = '2026-11-10T11:00:00+00:00';

    public function test_ListsTheRecipientsNotificationsNewestFirst(): void
    {
        $this->storeNotification(id: self::notificationId(sequence: 1), recipientId: Mother::REQUESTER_ID);
        $this->storeNotification(id: self::notificationId(sequence: 2), recipientId: Mother::REQUESTER_ID);
        $this->storeNotification(id: self::notificationId(sequence: 3), recipientId: Mother::OTHER_RECIPIENT_ID);

        $page = $this->list(isUnreadOnly: false, cursor: null, perPage: 20);

        $first = $page->notifications[0];
        $newestFirst = [self::notificationId(sequence: 2), self::notificationId(sequence: 1)];

        self::assertSame($newestFirst, self::idsOf(page: $page));
        self::assertSame('travel_request.approved', $first->type);
        self::assertSame('Seu pedido de viagem para Wano foi aprovado.', $first->message);
        self::assertSame(['travel_request_id' => Mother::TRAVEL_REQUEST_ID, 'status' => 'approved'], $first->details);
        self::assertNull($first->readAt);
        self::assertEquals(new DateTimeImmutable('2026-11-10T09:30:00+00:00'), $first->createdAt);
        self::assertNull($page->nextCursor);
        self::assertNull($page->previousCursor);
    }

    public function test_ListsOnlyTheUnreadWhenAsked(): void
    {
        $this->storeNotification(
            id: self::notificationId(sequence: 1),
            recipientId: Mother::REQUESTER_ID,
            readAt: self::FIRST_READ_AT,
        );
        $this->storeNotification(id: self::notificationId(sequence: 2), recipientId: Mother::REQUESTER_ID);

        $page = $this->list(isUnreadOnly: true, cursor: null, perPage: 20);

        self::assertSame([self::notificationId(sequence: 2)], self::idsOf(page: $page));
    }

    public function test_PagesBackAndForthWithTheCursors(): void
    {
        for ($sequence = 1; $sequence <= 3; $sequence++) {
            $this->storeNotification(id: self::notificationId(sequence: $sequence), recipientId: Mother::REQUESTER_ID);
        }

        $firstPage = $this->list(isUnreadOnly: false, cursor: null, perPage: 2);
        $secondPage = $this->list(isUnreadOnly: false, cursor: $firstPage->nextCursor, perPage: 2);
        $backToFirst = $this->list(isUnreadOnly: false, cursor: $secondPage->previousCursor, perPage: 2);
        $newestTwo = [self::notificationId(sequence: 3), self::notificationId(sequence: 2)];

        self::assertSame($newestTwo, self::idsOf(page: $firstPage));
        self::assertNotNull($firstPage->nextCursor);
        self::assertNull($firstPage->previousCursor);
        self::assertSame([self::notificationId(sequence: 1)], self::idsOf(page: $secondPage));
        self::assertNull($secondPage->nextCursor);
        self::assertSame(self::idsOf(page: $firstPage), self::idsOf(page: $backToFirst));
        self::assertNotNull($backToFirst->nextCursor);
    }

    public function test_RejectsACursorItDidNotIssue(): void
    {
        $this->expectException(InvalidListCursor::class);

        $this->list(isUnreadOnly: false, cursor: 'not-a-cursor', perPage: 20);
    }

    public function test_MarksReadOnceAndKeepsTheFirstReadTime(): void
    {
        $this->storeNotification(id: self::notificationId(sequence: 1), recipientId: Mother::REQUESTER_ID);
        $notifications = $this->notifications();
        $recipientId = Uuid7::fromString(candidate: Mother::REQUESTER_ID);
        $notificationId = Uuid7::fromString(candidate: self::notificationId(sequence: 1));

        $read = $notifications->markRead(
            recipientId: $recipientId,
            notificationId: $notificationId,
            readAt: new DateTimeImmutable('2026-11-10T10:00:00+00:00'),
        );
        $readAgain = $notifications->markRead(
            recipientId: $recipientId,
            notificationId: $notificationId,
            readAt: new DateTimeImmutable(self::LATER_READ_AT),
        );

        self::assertEquals(new DateTimeImmutable('2026-11-10T10:00:00+00:00'), $read->readAt);
        self::assertEquals($read->readAt, $readAgain->readAt);
    }

    public function test_SomeoneElsesNotificationCannotBeMarkedRead(): void
    {
        $this->storeNotification(id: self::notificationId(sequence: 1), recipientId: Mother::OTHER_RECIPIENT_ID);

        $someoneElsesId = Uuid7::fromString(candidate: self::notificationId(sequence: 1));

        try {
            $this->notifications()
                ->markRead(
                    recipientId: Uuid7::fromString(candidate: Mother::REQUESTER_ID),
                    notificationId: $someoneElsesId,
                    readAt: new DateTimeImmutable(self::LATER_READ_AT),
                );
            $isNotFound = false;
        } catch (NotificationNotFound) {
            $isNotFound = true;
        }

        $readAt = $this->app->make(ConnectionInterface::class)
            ->table('notifications')
            ->value('read_at');

        self::assertTrue($isNotFound);
        self::assertNull($readAt);
    }

    private function notifications(): DatabaseNotifications
    {
        return new DatabaseNotifications(connection: $this->app->make(ConnectionInterface::class));
    }

    /**
     * @param positive-int $perPage
     */
    private function list(bool $isUnreadOnly, ?string $cursor, int $perPage): NotificationPage
    {
        $criteria = new NotificationListCriteria(
            recipientId: Uuid7::fromString(candidate: Mother::REQUESTER_ID),
            isUnreadOnly: $isUnreadOnly,
            cursor: $cursor,
            perPage: $perPage,
        );

        return $this->notifications()
            ->list(criteria: $criteria);
    }

    /**
     * @return list<string>
     */
    private static function idsOf(NotificationPage $page): array
    {
        return array_map(
            static fn(NotificationOutput $notification): string => $notification->id,
            $page->notifications,
        );
    }
}
