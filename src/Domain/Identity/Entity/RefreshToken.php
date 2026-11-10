<?php

declare(strict_types=1);

namespace Domain\Identity\Entity;

use DateTimeImmutable;
use Domain\Identity\Exception\InvalidRefreshToken;
use Domain\Identity\ValueObject\RefreshTokenFamilyId;
use Domain\Identity\ValueObject\RefreshTokenHash;
use Domain\Identity\ValueObject\RefreshTokenId;
use Domain\Identity\ValueObject\UserId;

/**
 * Every refresh starts a new token in the same family. A rotated token presented again means it leaked,
 * so the whole family is revoked.
 */
final readonly class RefreshToken
{
    private function __construct(
        public RefreshTokenId $id,
        public RefreshTokenFamilyId $familyId,
        public UserId $userId,
        public RefreshTokenHash $tokenHash,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $rotatedAt,
        public ?DateTimeImmutable $revokedAt,
    ) {}

    public static function issue(
        RefreshTokenId $id,
        RefreshTokenFamilyId $familyId,
        UserId $userId,
        RefreshTokenHash $tokenHash,
        DateTimeImmutable $issuedAt,
        DateTimeImmutable $expiresAt,
    ): self {
        return new self(
            id: $id,
            familyId: $familyId,
            userId: $userId,
            tokenHash: $tokenHash,
            issuedAt: $issuedAt,
            expiresAt: $expiresAt,
            rotatedAt: null,
            revokedAt: null,
        );
    }

    public static function reconstitute(
        RefreshTokenId $id,
        RefreshTokenFamilyId $familyId,
        UserId $userId,
        RefreshTokenHash $tokenHash,
        DateTimeImmutable $issuedAt,
        DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $rotatedAt,
        ?DateTimeImmutable $revokedAt,
    ): self {
        return new self(
            id: $id,
            familyId: $familyId,
            userId: $userId,
            tokenHash: $tokenHash,
            issuedAt: $issuedAt,
            expiresAt: $expiresAt,
            rotatedAt: $rotatedAt,
            revokedAt: $revokedAt,
        );
    }

    public function wasRotated(): bool
    {
        return $this->rotatedAt !== null;
    }

    /**
     * Rotation is checked by the caller first, because a reused token must revoke its family.
     *
     * @throws InvalidRefreshToken
     */
    public function assertUsableAt(DateTimeImmutable $moment): void
    {
        if ($this->revokedAt !== null) {
            throw InvalidRefreshToken::revoked();
        }

        if ($moment >= $this->expiresAt) {
            throw InvalidRefreshToken::expired();
        }
    }
}
