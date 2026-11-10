<?php

declare(strict_types=1);

namespace Application\Identity\Input;

use DateTimeImmutable;
use SensitiveParameter;

final readonly class LogoutInput
{
    public function __construct(
        public string $userId,
        public string $accessTokenId,
        public DateTimeImmutable $accessTokenExpiresAt,
        #[SensitiveParameter]
        public string $refreshToken,
    ) {}
}
