<?php

declare(strict_types=1);

namespace Application\Notification\UseCase;

use Application\Notification\Exception\NotificationNotFound;
use Application\Notification\Input\MarkNotificationReadInput;
use Application\Notification\Output\NotificationOutput;
use Application\Notification\Port\Notifications;
use Application\Shared\Port\Clock;
use Domain\Shared\Exception\InvalidIdentifier;
use Domain\Shared\ValueObject\Uuid7;

final readonly class MarkNotificationReadUseCase
{
    public function __construct(
        private Notifications $notifications,
        private Clock $clock,
    ) {}

    /**
     * @throws InvalidIdentifier
     * @throws NotificationNotFound
     */
    public function execute(MarkNotificationReadInput $input): NotificationOutput
    {
        return $this->notifications->markRead(
            recipientId: Uuid7::fromString(candidate: $input->recipientId),
            notificationId: Uuid7::fromString(candidate: $input->notificationId),
            readAt: $this->clock->now(),
        );
    }
}
