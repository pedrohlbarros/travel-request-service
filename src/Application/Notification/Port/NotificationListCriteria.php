<?php

declare(strict_types=1);

namespace Application\Notification\Port;

use Domain\Shared\ValueObject\Uuid7;

final readonly class NotificationListCriteria
{
    /**
     * @param positive-int $perPage
     */
    public function __construct(
        public Uuid7 $recipientId,
        public bool $isUnreadOnly,
        public ?string $cursor,
        public int $perPage,
    ) {}
}
