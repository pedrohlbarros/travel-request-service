<?php

declare(strict_types=1);

namespace Interfaces\Http\Identity\Guard;

use Application\Identity\Exception\InvalidAccessToken;
use Application\Identity\UseCase\AuthenticateAccessTokenUseCase;
use Illuminate\Http\Request;
use Interfaces\Http\Shared\Auth\AuthenticatedUser;

final readonly class BearerTokenAuthenticator
{
    public function __construct(
        private AuthenticateAccessTokenUseCase $authenticate,
    ) {}

    public function __invoke(Request $request): ?AuthenticatedUser
    {
        $bearerToken = $request->bearerToken();

        if ($bearerToken === null) {
            return null;
        }

        try {
            $authentication = $this->authenticate->execute(accessToken: $bearerToken);
        } catch (InvalidAccessToken) {
            return null;
        }

        return new AuthenticatedUser(
            id: $authentication->userId,
            isAdmin: $authentication->isAdmin,
            accessTokenId: $authentication->accessTokenId,
            accessTokenExpiresAt: $authentication->accessTokenExpiresAt,
        );
    }
}
