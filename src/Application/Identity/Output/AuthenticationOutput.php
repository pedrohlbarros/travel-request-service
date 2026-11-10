<?php

declare(strict_types=1);

namespace Application\Identity\Output;

use DateTimeImmutable;

final readonly class AuthenticationOutput
{
    public function __construct(
        public string $userId,
        public bool $isAdmin,
        public string $accessTokenId,
        public DateTimeImmutable $accessTokenExpiresAt,
    ) {}
}
