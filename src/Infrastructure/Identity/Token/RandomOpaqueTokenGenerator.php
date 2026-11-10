<?php

declare(strict_types=1);

namespace Infrastructure\Identity\Token;

use Application\Identity\Port\OpaqueTokenGenerator;

final readonly class RandomOpaqueTokenGenerator implements OpaqueTokenGenerator
{
    private const int RANDOM_BYTES = 32;

    public function generate(): string
    {
        $token = Base64Url::encode(bytes: random_bytes(self::RANDOM_BYTES));
        \assert($token !== '');

        return $token;
    }
}
