<?php

declare(strict_types=1);

namespace Application\Identity\Port;

final readonly class IssuedAccessToken
{
    public function __construct(
        public string $token,
        public string $tokenId,
    ) {}
}
