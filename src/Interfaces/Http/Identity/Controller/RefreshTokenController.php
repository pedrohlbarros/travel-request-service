<?php

declare(strict_types=1);

namespace Interfaces\Http\Identity\Controller;

use Application\Identity\UseCase\RefreshAccessTokenUseCase;
use Interfaces\Http\Identity\Request\RefreshTokenRequest;
use Interfaces\Http\Identity\Resource\TokenPairResource;

final readonly class RefreshTokenController
{
    public function __construct(
        private RefreshAccessTokenUseCase $refreshAccessToken,
    ) {}

    public function __invoke(RefreshTokenRequest $request): TokenPairResource
    {
        $tokenPair = $this->refreshAccessToken->execute(refreshToken: $request->refreshToken());

        return new TokenPairResource(tokenPair: $tokenPair);
    }
}
