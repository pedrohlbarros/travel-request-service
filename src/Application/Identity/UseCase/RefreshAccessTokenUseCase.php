<?php

declare(strict_types=1);

namespace Application\Identity\UseCase;

use Application\Identity\Output\TokenPairOutput;
use Application\Identity\Service\TokenPairIssuer;
use Application\Shared\Port\Clock;
use Domain\Identity\Exception\InvalidRefreshToken;
use Domain\Identity\Repository\RefreshTokenRepository;
use Domain\Identity\ValueObject\RefreshTokenHash;
use SensitiveParameter;

final readonly class RefreshAccessTokenUseCase
{
    public function __construct(
        private RefreshTokenRepository $refreshTokens,
        private TokenPairIssuer $tokenPairs,
        private Clock $clock,
    ) {}

    /**
     * @throws InvalidRefreshToken
     */
    public function execute(#[SensitiveParameter] string $refreshToken): TokenPairOutput
    {
        $now = $this->clock->now();
        $tokenHash = RefreshTokenHash::of(refreshToken: $refreshToken);
        $storedToken = $this->refreshTokens->findByHash(tokenHash: $tokenHash);

        if ($storedToken === null) {
            throw InvalidRefreshToken::unknown();
        }

        $storedToken->assertUsableAt(moment: $now);

        $isRotationWon = $this->refreshTokens->markRotated(refreshToken: $storedToken, rotatedAt: $now);

        if (! $isRotationWon) {
            $this->refreshTokens->revokeFamily(familyId: $storedToken->familyId, revokedAt: $now);

            throw InvalidRefreshToken::reused();
        }

        return $this->tokenPairs->issue(userId: $storedToken->userId, familyId: $storedToken->familyId, issuedAt: $now);
    }
}
