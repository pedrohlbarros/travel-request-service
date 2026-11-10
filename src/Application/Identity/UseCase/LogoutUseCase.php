<?php

declare(strict_types=1);

namespace Application\Identity\UseCase;

use Application\Identity\Input\LogoutInput;
use Application\Identity\Port\AccessTokenRevocationList;
use Application\Shared\Port\Clock;
use Domain\Identity\Repository\RefreshTokenRepository;
use Domain\Identity\ValueObject\RefreshTokenHash;
use Domain\Identity\ValueObject\UserId;

final readonly class LogoutUseCase
{
    public function __construct(
        private AccessTokenRevocationList $revokedAccessTokens,
        private RefreshTokenRepository $refreshTokens,
        private Clock $clock,
    ) {}

    /**
     * A refresh token that is unknown or belongs to someone else is ignored: logout still ends the current access.
     */
    public function execute(LogoutInput $input): void
    {
        $this->revokedAccessTokens->revoke(tokenId: $input->accessTokenId, until: $input->accessTokenExpiresAt);

        $storedToken = $this->refreshTokens->findByHash(tokenHash: RefreshTokenHash::of(refreshToken: $input->refreshToken));

        if ($storedToken?->userId->equals(other: UserId::fromString(id: $input->userId)) === true) {
            $this->refreshTokens->revokeFamily(familyId: $storedToken->familyId, revokedAt: $this->clock->now());
        }
    }
}
