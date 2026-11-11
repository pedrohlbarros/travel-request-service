<?php

declare(strict_types=1);

namespace Interfaces\Http\Notification\Controller;

use Application\Notification\UseCase\ListNotificationsUseCase;
use Application\Shared\Exception\InvalidListCursor;
use Interfaces\Http\Notification\Request\ListNotificationsRequest;
use Interfaces\Http\Notification\Resource\NotificationPageResource;
use Interfaces\Http\Shared\Auth\CurrentUser;

final readonly class ListNotificationsController
{
    public function __construct(
        private ListNotificationsUseCase $listNotifications,
    ) {}

    /**
     * The caller's own notifications, newest first; admins included.
     *
     * @throws InvalidListCursor
     */
    public function __invoke(ListNotificationsRequest $request): NotificationPageResource
    {
        $caller = CurrentUser::of(request: $request);
        $page = $this->listNotifications->execute(input: $request->toInput(recipientId: $caller->id));

        return new NotificationPageResource(page: $page);
    }
}
