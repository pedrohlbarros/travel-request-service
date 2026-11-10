<?php

declare(strict_types=1);

namespace Application\Identity\Port;

use DateTimeImmutable;

final readonly class VerifiedAccessToken
{
    public function __construct(
        public string $userId,
        public string $tokenId,
        public DateTimeImmutable $expiresAt,
    ) {}
}
