<?php

declare(strict_types=1);

namespace Application\Identity\Port;

use DateTimeImmutable;
use Domain\Identity\ValueObject\UserId;

interface AccessTokenIssuer
{
    public function issue(UserId $userId, DateTimeImmutable $issuedAt, DateTimeImmutable $expiresAt): IssuedAccessToken;
}
