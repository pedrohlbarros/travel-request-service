<?php

declare(strict_types=1);

namespace Application\Notification\Output;

use DateTimeImmutable;

final readonly class NotificationOutput
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $message,
        public array $details,
        public ?DateTimeImmutable $readAt,
        public DateTimeImmutable $createdAt,
    ) {}
}
