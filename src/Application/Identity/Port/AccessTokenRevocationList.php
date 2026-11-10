<?php

declare(strict_types=1);

namespace Application\Identity\Port;

use DateTimeImmutable;

interface AccessTokenRevocationList
{
    public function revoke(string $tokenId, DateTimeImmutable $until): void;

    public function isRevoked(string $tokenId): bool;
}
