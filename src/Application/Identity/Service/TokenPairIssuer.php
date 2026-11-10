<?php

declare(strict_types=1);

namespace Application\Identity\Service;

use Application\Identity\Config\TokenLifetimes;
use Application\Identity\Output\TokenPairOutput;
use Application\Identity\Port\AccessTokenIssuer;
use Application\Identity\Port\OpaqueTokenGenerator;
use Application\Shared\Port\IdentifierGenerator;
use DateInterval;
use DateTimeImmutable;
use Domain\Identity\Entity\RefreshToken;
use Domain\Identity\Repository\RefreshTokenRepository;
use Domain\Identity\ValueObject\RefreshTokenFamilyId;
use Domain\Identity\ValueObject\RefreshTokenHash;
use Domain\Identity\ValueObject\RefreshTokenId;
use Domain\Identity\ValueObject\UserId;

final readonly class TokenPairIssuer
{
    public function __construct(
        private AccessTokenIssuer $accessTokens,
        private OpaqueTokenGenerator $opaqueTokens,
        private RefreshTokenRepository $refreshTokens,
        private IdentifierGenerator $identifiers,
        private TokenLifetimes $lifetimes,
    ) {}

    public function issue(UserId $userId, RefreshTokenFamilyId $familyId, DateTimeImmutable $issuedAt): TokenPairOutput
    {
        $accessToken = $this->accessTokens->issue(
            userId: $userId,
            issuedAt: $issuedAt,
            expiresAt: $this->secondsAfter(moment: $issuedAt, seconds: $this->lifetimes->accessTokenSeconds),
        );

        $plainRefreshToken = $this->opaqueTokens->generate();
        $refreshTokenExpiresAt = $this->secondsAfter(moment: $issuedAt, seconds: $this->lifetimes->refreshTokenSeconds);
        $refreshTokenId = RefreshTokenId::fromString(id: $this->identifiers->uuid7());
        $refreshToken = RefreshToken::issue(
            id: $refreshTokenId,
            familyId: $familyId,
            userId: $userId,
            tokenHash: RefreshTokenHash::of(refreshToken: $plainRefreshToken),
            issuedAt: $issuedAt,
            expiresAt: $refreshTokenExpiresAt,
        );
        $this->refreshTokens->add(refreshToken: $refreshToken);

        return new TokenPairOutput(
            accessToken: $accessToken->token,
            accessTokenExpiresIn: $this->lifetimes->accessTokenSeconds,
            refreshToken: $plainRefreshToken,
            refreshTokenExpiresIn: $this->lifetimes->refreshTokenSeconds,
        );
    }

    private function secondsAfter(DateTimeImmutable $moment, int $seconds): DateTimeImmutable
    {
        $interval = new DateInterval(\sprintf('PT%dS', $seconds));

        return $moment->add($interval);
    }
}
