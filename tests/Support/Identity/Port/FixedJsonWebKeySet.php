<?php

declare(strict_types=1);

namespace Tests\Support\Identity\Port;

use Application\Identity\Port\JsonWebKeySet;

final readonly class FixedJsonWebKeySet implements JsonWebKeySet
{
    /**
     * @param list<array<string, string>> $publicKeys
     */
    public function __construct(
        private array $publicKeys,
    ) {}

    public function publicKeys(): array
    {
        return $this->publicKeys;
    }
}
