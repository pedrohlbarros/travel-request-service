<?php

declare(strict_types=1);

namespace Application\Identity\Port;

interface JsonWebKeySet
{
    /**
     * @return list<array<string, string>> public keys as JWK (RFC 7517)
     */
    public function publicKeys(): array;
}
