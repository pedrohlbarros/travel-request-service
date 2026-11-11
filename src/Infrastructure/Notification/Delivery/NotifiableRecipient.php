<?php

declare(strict_types=1);

namespace Infrastructure\Notification\Delivery;

use Domain\Notification\ValueObject\Recipient;
use Illuminate\Notifications\RoutesNotifications;

/**
 * The requester as Laravel's notifications see them, built from the event alone: never loaded from users.
 */
final class NotifiableRecipient
{
    use RoutesNotifications;

    public function __construct(
        public readonly Recipient $recipient,
    ) {}

    /**
     * @return array<string, string>
     */
    public function routeNotificationForMail(): array
    {
        return [$this->recipient->email => $this->recipient->name];
    }
}
