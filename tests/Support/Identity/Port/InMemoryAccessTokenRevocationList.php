<?php

declare(strict_types=1);

namespace Tests\Support\Identity\Port;

use Application\Identity\Port\AccessTokenRevocationList;
use DateTimeImmutable;

final class InMemoryAccessTokenRevocationList implements AccessTokenRevocationList
{
    /** @var array<string, DateTimeImmutable> */
    public private(set) array $revokedUntilByTokenId = [];

    public function revoke(string $tokenId, DateTimeImmutable $until): void
    {
        $this->revokedUntilByTokenId[$tokenId] = $until;
    }

    public function isRevoked(string $tokenId): bool
    {
        return \array_key_exists($tokenId, $this->revokedUntilByTokenId);
    }
}
