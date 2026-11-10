<?php

declare(strict_types=1);

namespace Application\Identity\UseCase;

use Application\Identity\Exception\InvalidAccessToken;
use Application\Identity\Output\AuthenticationOutput;
use Application\Identity\Port\AccessTokenRevocationList;
use Application\Identity\Port\AccessTokenVerifier;
use Application\Shared\Port\Clock;
use Domain\Identity\Exception\UserNotFound;
use Domain\Identity\Repository\UserRepository;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\Exception\InvalidIdentifier;

final readonly class AuthenticateAccessTokenUseCase
{
    public function __construct(
        private AccessTokenVerifier $verifier,
        private AccessTokenRevocationList $revokedAccessTokens,
        private UserRepository $users,
        private Clock $clock,
    ) {}

    /**
     * The role is read from the stored user, not from the token, so a role change applies immediately.
     *
     * @throws InvalidAccessToken
     */
    public function execute(string $accessToken): AuthenticationOutput
    {
        $verifiedToken = $this->verifier->verify(token: $accessToken, now: $this->clock->now());

        if ($this->revokedAccessTokens->isRevoked(tokenId: $verifiedToken->tokenId)) {
            throw InvalidAccessToken::because(reason: 'it was revoked');
        }

        try {
            $user = $this->users->get(id: UserId::fromString(id: $verifiedToken->userId));
        } catch (InvalidIdentifier|UserNotFound) {
            throw InvalidAccessToken::because(reason: 'its subject is not a user');
        }

        return new AuthenticationOutput(
            userId: $user->id->toString(),
            isAdmin: $user->isAdmin(),
            accessTokenId: $verifiedToken->tokenId,
            accessTokenExpiresAt: $verifiedToken->expiresAt,
        );
    }
}
