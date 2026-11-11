<?php

declare(strict_types=1);

namespace Application\Identity\UseCase;

use Application\Identity\Exception\InvalidAccessToken;
use Application\Identity\Output\AuthenticationOutput;
use Application\Identity\Port\AccessTokenRevocationList;
use Application\Identity\Port\AccessTokenVerifier;
use Application\Identity\Port\UserRoles;
use Application\Shared\Port\Clock;
use Domain\Identity\Enum\Role;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\Exception\InvalidIdentifier;

final readonly class AuthenticateAccessTokenUseCase
{
    public function __construct(
        private AccessTokenVerifier $verifier,
        private AccessTokenRevocationList $revokedAccessTokens,
        private UserRoles $roles,
        private Clock $clock,
    ) {}

    /**
     * The role is read from the stored user, not from the token, so the token carries no authorization.
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
            $userId = UserId::fromString(id: $verifiedToken->userId);
        } catch (InvalidIdentifier) {
            throw InvalidAccessToken::because(reason: 'its subject is not a user');
        }

        $role = $this->roles->roleOf(id: $userId);

        if ($role === null) {
            throw InvalidAccessToken::because(reason: 'its subject is not a user');
        }

        return new AuthenticationOutput(
            userId: $userId->toString(),
            isAdmin: $role === Role::Admin,
            accessTokenId: $verifiedToken->tokenId,
            accessTokenExpiresAt: $verifiedToken->expiresAt,
        );
    }
}
