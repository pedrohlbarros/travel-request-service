<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Identity\Token;

use Illuminate\Redis\RedisManager;
use Illuminate\Support\Str;
use Infrastructure\Identity\Token\RedisAccessTokenRevocationList;
use Tests\Support\Identity\Fixture\IdentityMother as Mother;
use Tests\Support\LaravelTestCase;
use Tests\Support\Shared\Port\FrozenClock;

final class RedisAccessTokenRevocationListTest extends LaravelTestCase
{
    private const string KEY_PREFIX = 'identity:revoked-access-token:';

    private const int SECONDS_UNTIL_EXPIRY = 900;

    public function test_RevokedTokenStaysRevokedUntilItExpires(): void
    {
        $revocationList = $this->revocationList();
        $tokenId = Str::uuid7()
            ->toString();

        $revocationList->revoke(
            tokenId: $tokenId,
            until: Mother::moment()
                ->modify('+' . self::SECONDS_UNTIL_EXPIRY . ' seconds'),
        );

        $isRevoked = $revocationList->isRevoked(tokenId: $tokenId);
        $remainingSeconds = $this->redis()
            ->connection()
            ->command('ttl', [self::KEY_PREFIX . $tokenId]);

        self::assertTrue($isRevoked);
        self::assertSame(self::SECONDS_UNTIL_EXPIRY, $remainingSeconds);
    }

    public function test_AlreadyExpiredTokenIsKeptForOneSecond(): void
    {
        $revocationList = $this->revocationList();
        $tokenId = Str::uuid7()
            ->toString();

        $revocationList->revoke(
            tokenId: $tokenId,
            until: Mother::moment()
                ->modify('-1 minute'),
        );

        self::assertSame(
            1,
            $this->redis()
                ->connection()
                ->command('ttl', [self::KEY_PREFIX . $tokenId]),
        );
    }

    public function test_UnknownTokenIsNotRevoked(): void
    {
        $neverRevokedTokenId = Str::uuid7()
            ->toString();

        $isRevoked = $this->revocationList()
            ->isRevoked(tokenId: $neverRevokedTokenId);

        self::assertFalse($isRevoked);
    }

    private function revocationList(): RedisAccessTokenRevocationList
    {
        $clock = new FrozenClock(frozenAt: Mother::moment());

        return new RedisAccessTokenRevocationList(redis: $this->redis(), clock: $clock);
    }

    private function redis(): RedisManager
    {
        return $this->app->make(RedisManager::class);
    }
}
