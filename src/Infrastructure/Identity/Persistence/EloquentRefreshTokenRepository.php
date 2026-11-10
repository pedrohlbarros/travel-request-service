<?php

declare(strict_types=1);

namespace Infrastructure\Identity\Persistence;

use DateTimeImmutable;
use Domain\Identity\Entity\RefreshToken;
use Domain\Identity\Repository\RefreshTokenRepository;
use Domain\Identity\ValueObject\RefreshTokenFamilyId;
use Domain\Identity\ValueObject\RefreshTokenHash;
use Domain\Identity\ValueObject\RefreshTokenId;
use Domain\Identity\ValueObject\UserId;
use Infrastructure\Shared\Persistence\BinaryUuid;

final readonly class EloquentRefreshTokenRepository implements RefreshTokenRepository
{
    private const int ROWS_CHANGED_BY_A_WON_RACE = 1;

    public function add(RefreshToken $refreshToken): void
    {
        RefreshTokenModel::query()
            ->create([
                'id' => $refreshToken->id->toString(),
                'family_id' => $refreshToken->familyId->toString(),
                'user_id' => $refreshToken->userId->toString(),
                'token_hash' => $refreshToken->tokenHash->value,
                'issued_at' => $refreshToken->issuedAt,
                'expires_at' => $refreshToken->expiresAt,
            ]);
    }

    public function findByHash(RefreshTokenHash $tokenHash): ?RefreshToken
    {
        $tokenRow = RefreshTokenModel::query()
            ->where('token_hash', $tokenHash->value)
            ->first();

        return $tokenRow === null ? null : $this->toEntity(tokenRow: $tokenRow);
    }

    public function markRotated(RefreshToken $refreshToken, DateTimeImmutable $rotatedAt): bool
    {
        $tokenIdBytes = BinaryUuid::toBytes(uuid: $refreshToken->id->toString());
        $rotatedRows = RefreshTokenModel::query()
            ->whereKey($tokenIdBytes)
            ->where('rotated_at', null)
            ->where('revoked_at', null)
            ->update(['rotated_at' => $rotatedAt]);

        return $rotatedRows === self::ROWS_CHANGED_BY_A_WON_RACE;
    }

    public function revokeFamily(RefreshTokenFamilyId $familyId, DateTimeImmutable $revokedAt): void
    {
        $familyIdBytes = BinaryUuid::toBytes(uuid: $familyId->toString());

        RefreshTokenModel::query()
            ->where('family_id', $familyIdBytes)
            ->where('revoked_at', null)
            ->update(['revoked_at' => $revokedAt]);
    }

    private function toEntity(RefreshTokenModel $tokenRow): RefreshToken
    {
        return RefreshToken::reconstitute(
            id: RefreshTokenId::fromString(id: $tokenRow->id),
            familyId: RefreshTokenFamilyId::fromString(id: $tokenRow->family_id),
            userId: UserId::fromString(id: $tokenRow->user_id),
            tokenHash: RefreshTokenHash::fromStored(storedHash: $tokenRow->token_hash),
            issuedAt: $tokenRow->issued_at->toDateTimeImmutable(),
            expiresAt: $tokenRow->expires_at->toDateTimeImmutable(),
            rotatedAt: $tokenRow->rotated_at?->toDateTimeImmutable(),
            revokedAt: $tokenRow->revoked_at?->toDateTimeImmutable(),
        );
    }
}
