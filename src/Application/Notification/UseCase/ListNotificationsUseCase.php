<?php

declare(strict_types=1);

namespace Application\Notification\UseCase;

use Application\Notification\Input\ListNotificationsInput;
use Application\Notification\Output\NotificationPage;
use Application\Notification\Port\NotificationListCriteria;
use Application\Notification\Port\Notifications;
use Application\Shared\Exception\InvalidListCursor;
use Domain\Shared\Exception\InvalidIdentifier;
use Domain\Shared\ValueObject\Uuid7;

final readonly class ListNotificationsUseCase
{
    public function __construct(
        private Notifications $notifications,
    ) {}

    /**
     * Everyone, admins included, sees only their own notifications.
     *
     * @throws InvalidIdentifier
     * @throws InvalidListCursor
     */
    public function execute(ListNotificationsInput $input): NotificationPage
    {
        $criteria = new NotificationListCriteria(
            recipientId: Uuid7::fromString(candidate: $input->recipientId),
            isUnreadOnly: $input->isUnreadOnly,
            cursor: $input->cursor,
            perPage: $input->perPage,
        );

        return $this->notifications->list(criteria: $criteria);
    }
}
