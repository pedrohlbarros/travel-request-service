<?php

declare(strict_types=1);

namespace Application\Identity\Config;

final readonly class TokenLifetimes
{
    /**
     * @param positive-int $accessTokenSeconds
     * @param positive-int $refreshTokenSeconds
     */
    public function __construct(
        public int $accessTokenSeconds,
        public int $refreshTokenSeconds,
    ) {}
}
