<?php

declare(strict_types=1);

namespace Tests\Application\Notification\UseCase;

use Application\Notification\Exception\NotificationNotFound;
use Application\Notification\Input\MarkNotificationReadInput;
use Application\Notification\Output\NotificationOutput;
use Application\Notification\UseCase\MarkNotificationReadUseCase;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\Notification\Fixture\DecisionMother as Mother;
use Tests\Support\Notification\Port\InMemoryNotifications;
use Tests\Support\Shared\Port\FrozenClock;

final class MarkNotificationReadUseCaseTest extends TestCase
{
    private const string READ_AT = '2026-11-10T12:00:00+00:00';

    private InMemoryNotifications $notifications;

    private MarkNotificationReadUseCase $useCase;

    protected function setUp(): void
    {
        $this->notifications = new InMemoryNotifications();
        $unread = new NotificationOutput(
            id: Mother::EVENT_ID,
            type: 'travel_request.approved',
            message: 'Aprovado.',
            details: [],
            readAt: null,
            createdAt: new DateTimeImmutable(Mother::DECIDED_AT),
        );
        $this->notifications->store(recipientId: Mother::REQUESTER_ID, notification: $unread);
        $readAt = new DateTimeImmutable(self::READ_AT);
        $this->useCase = new MarkNotificationReadUseCase(
            notifications: $this->notifications,
            clock: new FrozenClock(frozenAt: $readAt),
        );
    }

    public function test_MarksTheNotificationReadNow(): void
    {
        $read = $this->useCase->execute(input: new MarkNotificationReadInput(
            recipientId: Mother::REQUESTER_ID,
            notificationId: Mother::EVENT_ID,
        ));

        self::assertSame(Mother::EVENT_ID, $read->id);
        self::assertEquals(new DateTimeImmutable(self::READ_AT), $read->readAt);
    }

    public function test_SomeoneElsesNotificationIsNotFound(): void
    {
        $this->expectException(NotificationNotFound::class);

        $this->useCase->execute(input: new MarkNotificationReadInput(
            recipientId: Mother::OTHER_RECIPIENT_ID,
            notificationId: Mother::EVENT_ID,
        ));
    }
}
