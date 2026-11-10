<?php

declare(strict_types=1);

namespace Application\Identity\Output;

final readonly class TokenPairOutput
{
    public function __construct(
        public string $accessToken,
        public int $accessTokenExpiresIn,
        public string $refreshToken,
        public int $refreshTokenExpiresIn,
    ) {}
}
