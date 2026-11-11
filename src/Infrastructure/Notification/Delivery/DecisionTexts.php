<?php

declare(strict_types=1);

namespace Infrastructure\Notification\Delivery;

final readonly class DecisionTexts
{
    public function __construct(
        public string $locale,
        public string $subject,
        public string $greeting,
        public string $message,
        public string $salutation,
    ) {}
}
