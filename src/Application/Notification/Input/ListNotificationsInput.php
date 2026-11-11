<?php

declare(strict_types=1);

namespace Application\Notification\Input;

final readonly class ListNotificationsInput
{
    /**
     * @param positive-int $perPage
     */
    public function __construct(
        public string $recipientId,
        public bool $isUnreadOnly,
        public ?string $cursor,
        public int $perPage,
    ) {}
}
