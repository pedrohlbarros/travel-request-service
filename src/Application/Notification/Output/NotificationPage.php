<?php

declare(strict_types=1);

namespace Application\Notification\Output;

final readonly class NotificationPage
{
    /**
     * @param list<NotificationOutput> $notifications
     * @param positive-int $perPage
     * @param ?string $nextCursor null on the last page
     * @param ?string $previousCursor null on the first page
     */
    public function __construct(
        public array $notifications,
        public int $perPage,
        public ?string $nextCursor,
        public ?string $previousCursor,
    ) {}
}
