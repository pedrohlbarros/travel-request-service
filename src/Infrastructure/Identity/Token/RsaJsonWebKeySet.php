<?php

declare(strict_types=1);

namespace Infrastructure\Identity\Token;

use Application\Identity\Port\JsonWebKeySet;

final readonly class RsaJsonWebKeySet implements JsonWebKeySet
{
    public function __construct(
        private JwtKeyPair $keys,
    ) {}

    public function publicKeys(): array
    {
        return [[
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => $this->keys->keyId,
            'n' => $this->keys->modulus,
            'e' => $this->keys->exponent,
        ]];
    }
}
