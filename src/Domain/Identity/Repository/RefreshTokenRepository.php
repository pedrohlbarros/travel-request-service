<?php

declare(strict_types=1);

namespace Domain\Identity\Repository;

use DateTimeImmutable;
use Domain\Identity\Entity\RefreshToken;
use Domain\Identity\ValueObject\RefreshTokenFamilyId;
use Domain\Identity\ValueObject\RefreshTokenHash;

interface RefreshTokenRepository
{
    public function add(RefreshToken $refreshToken): void;

    public function findByHash(RefreshTokenHash $tokenHash): ?RefreshToken;

    /**
     * Marks the token as rotated only if nobody rotated it first.
     *
     * @return bool false when a concurrent request already rotated it
     */
    public function markRotated(RefreshToken $refreshToken, DateTimeImmutable $rotatedAt): bool;

    public function revokeFamily(RefreshTokenFamilyId $familyId, DateTimeImmutable $revokedAt): void;
}
