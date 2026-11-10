<?php

declare(strict_types=1);

namespace Tests\Application\Identity\UseCase;

use Application\Identity\UseCase\GetJsonWebKeySetUseCase;
use PHPUnit\Framework\TestCase;
use Tests\Support\Identity\Port\FixedJsonWebKeySet;

final class GetJsonWebKeySetUseCaseTest extends TestCase
{
    public function test_ReturnsThePublishedKeys(): void
    {
        $publicKeys = [['kty' => 'RSA', 'kid' => 'key-1', 'n' => 'modulus', 'e' => 'AQAB']];

        $keySet = new GetJsonWebKeySetUseCase(keySet: new FixedJsonWebKeySet(publicKeys: $publicKeys))->execute();

        self::assertSame($publicKeys, $keySet->keys);
    }
}
