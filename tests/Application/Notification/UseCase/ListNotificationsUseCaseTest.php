<?php

declare(strict_types=1);

namespace Tests\Application\Notification\UseCase;

use Application\Notification\Input\ListNotificationsInput;
use Application\Notification\Output\NotificationOutput;
use Application\Notification\Output\NotificationPage;
use Application\Notification\UseCase\ListNotificationsUseCase;
use DateTimeImmutable;
use Domain\Shared\Exception\InvalidIdentifier;
use PHPUnit\Framework\TestCase;
use Tests\Support\Notification\Fixture\DecisionMother as Mother;
use Tests\Support\Notification\Port\InMemoryNotifications;

final class ListNotificationsUseCaseTest extends TestCase
{
    private const string CURSOR = 'opaque-cursor';

    private InMemoryNotifications $notifications;

    protected function setUp(): void
    {
        $this->notifications = new InMemoryNotifications();
        $read = $this->notification(id: Mother::EVENT_ID, isRead: true);
        $unread = $this->notification(id: Mother::OTHER_EVENT_ID, isRead: false);
        $someoneElses = $this->notification(id: Mother::TRAVEL_REQUEST_ID, isRead: false);
        $this->notifications->store(recipientId: Mother::REQUESTER_ID, notification: $read);
        $this->notifications->store(recipientId: Mother::REQUESTER_ID, notification: $unread);
        $this->notifications->store(recipientId: Mother::OTHER_RECIPIENT_ID, notification: $someoneElses);
    }

    public function test_ListsOnlyTheRecipientsOwnNotifications(): void
    {
        $page = $this->list(isUnreadOnly: false, perPage: 20);

        $ids = self::idsOf(page: $page);

        self::assertSame([Mother::EVENT_ID, Mother::OTHER_EVENT_ID], $ids);
        self::assertSame(20, $page->perPage);
        self::assertSame(self::CURSOR, $this->notifications->lastCursor);
    }

    public function test_CanListOnlyTheUnread(): void
    {
        $page = $this->list(isUnreadOnly: true, perPage: 20);

        $ids = self::idsOf(page: $page);

        self::assertSame([Mother::OTHER_EVENT_ID], $ids);
    }

    public function test_PassesThePageSize(): void
    {
        $page = $this->list(isUnreadOnly: false, perPage: 1);

        self::assertCount(1, $page->notifications);
        self::assertSame(1, $page->perPage);
    }

    public function test_RejectsARecipientIdThatIsNotUuid7(): void
    {
        $this->expectException(InvalidIdentifier::class);

        $input = new ListNotificationsInput(
            recipientId: 'nobody',
            isUnreadOnly: false,
            cursor: null,
            perPage: 20,
        );

        new ListNotificationsUseCase(notifications: $this->notifications)
            ->execute(input: $input);
    }

    /**
     * @param positive-int $perPage
     */
    private function list(bool $isUnreadOnly, int $perPage): NotificationPage
    {
        return new ListNotificationsUseCase(notifications: $this->notifications)
            ->execute(input: new ListNotificationsInput(
                recipientId: Mother::REQUESTER_ID,
                isUnreadOnly: $isUnreadOnly,
                cursor: self::CURSOR,
                perPage: $perPage,
            ));
    }

    /**
     * @return list<string>
     */
    private static function idsOf(NotificationPage $page): array
    {
        $ids = [];

        foreach ($page->notifications as $notification) {
            $ids[] = $notification->id;
        }

        return $ids;
    }

    private function notification(string $id, bool $isRead): NotificationOutput
    {
        $createdAt = new DateTimeImmutable(Mother::DECIDED_AT);

        return new NotificationOutput(
            id: $id,
            type: 'travel_request.approved',
            message: 'Aprovado.',
            details: [],
            readAt: $isRead ? $createdAt : null,
            createdAt: $createdAt,
        );
    }
}
