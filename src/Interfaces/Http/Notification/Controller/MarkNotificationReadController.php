<?php

declare(strict_types=1);

namespace Interfaces\Http\Notification\Controller;

use Application\Notification\Exception\NotificationNotFound;
use Application\Notification\Input\MarkNotificationReadInput;
use Application\Notification\UseCase\MarkNotificationReadUseCase;
use Illuminate\Http\Request;
use Interfaces\Http\Notification\NotificationHttpServiceProvider;
use Interfaces\Http\Notification\Resource\NotificationResource;
use Interfaces\Http\Shared\Auth\CurrentUser;

final readonly class MarkNotificationReadController
{
    public function __construct(
        private MarkNotificationReadUseCase $markRead,
    ) {}

    /**
     * Reading twice keeps the first read time. Someone else's notification is reported as missing.
     *
     * @throws NotificationNotFound
     */
    public function __invoke(Request $request): NotificationResource
    {
        $caller = CurrentUser::of(request: $request);
        $notificationId = $request->route(NotificationHttpServiceProvider::ID_PARAMETER);
        \assert(\is_string($notificationId));

        $notification = $this->markRead->execute(input: new MarkNotificationReadInput(
            recipientId: $caller->id,
            notificationId: $notificationId,
        ));

        return new NotificationResource(notification: $notification);
    }
}
