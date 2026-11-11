<?php

declare(strict_types=1);

namespace Application\Notification\Input;

final readonly class MarkNotificationReadInput
{
    public function __construct(
        public string $recipientId,
        public string $notificationId,
    ) {}
}
