<?php

declare(strict_types=1);

namespace Tests\Support\Identity\Repository;

use DateTimeImmutable;
use Domain\Identity\Entity\RefreshToken;
use Domain\Identity\Repository\RefreshTokenRepository;
use Domain\Identity\ValueObject\RefreshTokenFamilyId;
use Domain\Identity\ValueObject\RefreshTokenHash;

final class InMemoryRefreshTokenRepository implements RefreshTokenRepository
{
    /** @var array<string, RefreshToken> */
    private array $refreshTokenByHash = [];

    private bool $isNextRotationLostToAnotherRequest = false;

    public function add(RefreshToken $refreshToken): void
    {
        $this->refreshTokenByHash[$refreshToken->tokenHash->value] = $refreshToken;
    }

    public function findByHash(RefreshTokenHash $tokenHash): ?RefreshToken
    {
        return $this->refreshTokenByHash[$tokenHash->value] ?? null;
    }

    public function markRotated(RefreshToken $refreshToken, DateTimeImmutable $rotatedAt): bool
    {
        $storedToken = $this->refreshTokenByHash[$refreshToken->tokenHash->value];

        if ($this->isNextRotationLostToAnotherRequest) {
            $this->isNextRotationLostToAnotherRequest = false;
            $this->replace(storedToken: $storedToken, rotatedAt: $rotatedAt, revokedAt: $storedToken->revokedAt);

            return false;
        }

        if ($storedToken->wasRotated() || $storedToken->revokedAt !== null) {
            return false;
        }

        $this->replace(storedToken: $storedToken, rotatedAt: $rotatedAt, revokedAt: null);

        return true;
    }

    public function revokeFamily(RefreshTokenFamilyId $familyId, DateTimeImmutable $revokedAt): void
    {
        foreach ($this->refreshTokenByHash as $storedToken) {
            if ($storedToken->familyId->equals(other: $familyId) && $storedToken->revokedAt === null) {
                $this->replace(storedToken: $storedToken, rotatedAt: $storedToken->rotatedAt, revokedAt: $revokedAt);
            }
        }
    }

    /**
     * Makes the next rotation behave as if a concurrent request had rotated the token first.
     */
    public function loseNextRotationToAnotherRequest(): void
    {
        $this->isNextRotationLostToAnotherRequest = true;
    }

    /**
     * @return list<RefreshToken>
     */
    public function all(): array
    {
        return array_values($this->refreshTokenByHash);
    }

    private function replace(RefreshToken $storedToken, ?DateTimeImmutable $rotatedAt, ?DateTimeImmutable $revokedAt): void
    {
        $this->refreshTokenByHash[$storedToken->tokenHash->value] = RefreshToken::reconstitute(
            id: $storedToken->id,
            familyId: $storedToken->familyId,
            userId: $storedToken->userId,
            tokenHash: $storedToken->tokenHash,
            issuedAt: $storedToken->issuedAt,
            expiresAt: $storedToken->expiresAt,
            rotatedAt: $rotatedAt,
            revokedAt: $revokedAt,
        );
    }
}
