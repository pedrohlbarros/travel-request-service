<?php

declare(strict_types=1);

namespace Infrastructure\Identity\Token;

final readonly class JwtSettings
{
    /**
     * @param non-empty-string $issuer
     * @param non-empty-string $audience
     * @param non-empty-string $privateKeyPath
     * @param non-empty-string $publicKeyPath
     */
    public function __construct(
        public string $issuer,
        public string $audience,
        public string $privateKeyPath,
        public string $publicKeyPath,
    ) {}
}
